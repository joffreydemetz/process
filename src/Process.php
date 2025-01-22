<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Utils;

use JDZ\Utils\ProcessItem;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Process
{
    private float $start;
    private ?float $end = null;
    private ?float $time = null;
    private array $sections = [];
    private ?ProcessItem $currentSection = null;

    public static function singleton(): self
    {
        static $instance;
        if (!isset($instance)) {
            $instance = new self();
        }
        return $instance;
    }

    public function __construct()
    {
        $this->start = microtime(true);
    }

    public function getTime(): string
    {
        $this->close();
        return $this->formatTime();
    }

    public function startSection(string $name)
    {
        if ($this->currentSection) {
            $this->endSection();
        }

        $section = new ProcessItem($name);

        $this->sections[] = $section;
        $this->currentSection = $section;

        return $this;
    }

    public function startSubsection(string $name, bool $endPrevious = true)
    {
        if (true === $endPrevious && $this->currentSection) {
            $this->endSubsection();
        }

        if (!$this->currentSection) {
            throw new \Exception("No section has been started.");
        }

        $subsection = new ProcessItem($name);
        $this->currentSection->addChild($subsection);
        $this->currentSection = $subsection;

        return $this;
    }

    public function endSubsection()
    {
        if ($this->currentSection && $this->currentSection->parent) {
            $this->currentSection->close();
            $this->currentSection = $this->currentSection->parent;
        }

        return $this;
    }

    public function endSection()
    {
        if ($this->currentSection) {
            while ($this->currentSection->parent) {
                $this->endSubsection();
            }
            $this->currentSection->close();
            $this->currentSection = null;
        }

        return $this;
    }

    public function close()
    {
        $this->endSection();

        if (null === $this->end) {
            $endTime = microtime(true);
            $this->end = $endTime;
            $this->time = $endTime - $this->start;
        }
    }

    public function toArray(): array
    {
        $this->close();

        $list = [];
        $list[] = 'Total exec time : ' . $this->formatTime();
        foreach ($this->sections as $section) {
            $list[] = '---- ';
            $list = $section->export($list);
        }

        return $list;
    }

    private function formatTime(): string
    {
        $minutes = floor($this->time / 60);
        $remainingSeconds = (int)$this->time % 60;
        $milliseconds = round(($this->time - floor($this->time)) * 1000);

        $timeString = '';

        if ($minutes > 0) {
            $timeString .= sprintf("%d min ", $minutes);
        }

        if ($remainingSeconds > 0 || $minutes > 0) {
            $timeString .= sprintf("%d s ", $remainingSeconds);
        }

        if ($milliseconds > 0 || ($minutes > 0 || $remainingSeconds > 0)) {
            $timeString .= sprintf("%d ms ", $milliseconds);
        }

        return $timeString;
    }
}
