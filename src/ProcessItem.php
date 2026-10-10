<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Utils;

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
        return self::formatSeconds($this->time);
    }

    /**
     * A duration as "M min S s MS ms " (the larger units only when non-zero), or
     * "0ms". Shared with Process. Rounded to the millisecond before splitting, so
     * 0.9996 s is "1 s 0 ms " (it printed "1000 ms "); no time yet is "0ms".
     */
    public static function formatSeconds(?float $seconds): string
    {
        $totalMs = (int)round(($seconds ?? 0.0) * 1000);
        $minutes = intdiv($totalMs, 60000);
        $remainingSeconds = intdiv($totalMs % 60000, 1000);
        $milliseconds = $totalMs % 1000;

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
