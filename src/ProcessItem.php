<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Utils;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class ProcessItem
{
    public string $name;
    public ?ProcessItem $parent;
    public float $start;
    public ?float $end = null;
    public ?float $time = null;
    public array $children = [];

    public function __construct(string $name = 'ROOT', ?ProcessItem $parent = null)
    {
        $this->name = $name;
        $this->parent = $parent;
        $this->start = microtime(true);
    }

    public function findByName(string $name): bool
    {
        return $this->name === $name;
    }

    public function addChild(ProcessItem $child)
    {
        $child->parent = $this;
        $this->children[] = $child;
        return $this;
    }

    public function close()
    {
        foreach ($this->children as $child) {
            $child->close();
        }

        if (null === $this->end) {
            $endTime = microtime(true);
            $this->end = $endTime;
            $this->time = $endTime - $this->start;
        }

        return $this;
    }

    public function export(array $list = [], int $level = 0): array
    {
        $prefix = '';
        if ($level > 0) {
            $prefix .= '|' . \str_repeat('-', $level) . ' ';
        }

        $list[] = $prefix . $this->name . ' : ' . $this->formatTime();

        foreach ($this->children as $child) {
            $list = $child->export($list, $level + 1);
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

        if ('' === $timeString) {
            $timeString = '0ms';
        }

        return $timeString;
    }
}
