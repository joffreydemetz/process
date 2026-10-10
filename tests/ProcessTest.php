<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Utils\Tests;

use JDZ\Utils\Process;
use PHPUnit\Framework\TestCase;

/**
 * Process through its public API: the section tree shows in toArray(), so the
 * tests read it with every measured time masked as "T" (exact time formatting is
 * pinned in ProcessItemTest).
 */
class ProcessTest extends TestCase
{
    /** toArray() with each time replaced by "T" */
    private static function tree(Process $process): array
    {
        return array_map(fn(string $line) => preg_replace('/ : .+$/', ' : T', $line), $process->toArray());
    }

    public function testWithoutSectionsOnlyTheTotalIsReported(): void
    {
        $this->assertSame(['Total exec time : T'], self::tree(new Process()));
    }

    public function testAStartedSectionEndsThePreviousOne(): void
    {
        $process = (new Process())->startSection('Load')->startSection('Save');

        $this->assertSame(['Total exec time : T', '---- ', 'Load : T', '---- ', 'Save : T'], self::tree($process));
    }

    public function testASubsectionEndsThePreviousSubsectionByDefault(): void
    {
        $process = (new Process())->startSection('Main')->startSubsection('A')->startSubsection('B');

        $this->assertSame(['Total exec time : T', '---- ', 'Main : T', '|- A : T', '|- B : T'], self::tree($process));
    }

    public function testASubsectionCanNestInTheCurrentOne(): void
    {
        $process = (new Process())
            ->startSection('Main')
            ->startSubsection('A')
            ->startSubsection('A1', false)
            ->startSubsection('A1a', false);

        $this->assertSame(['Total exec time : T', '---- ', 'Main : T', '|- A : T', '|-- A1 : T', '|--- A1a : T'], self::tree($process));
    }

    public function testEndSubsectionMovesUpOneLevel(): void
    {
        $process = (new Process())
            ->startSection('Main')
            ->startSubsection('A')
            ->startSubsection('A1', false)
            ->endSubsection()
            ->startSubsection('A2', false);

        $this->assertSame(['Total exec time : T', '---- ', 'Main : T', '|- A : T', '|-- A1 : T', '|-- A2 : T'], self::tree($process));
    }

    public function testEndSubsectionAtTheSectionLevelDoesNothing(): void
    {
        $process = (new Process())->startSection('Main')->endSubsection()->startSubsection('A');

        $this->assertSame(['Total exec time : T', '---- ', 'Main : T', '|- A : T'], self::tree($process));
    }

    public function testEndSectionClosesEveryOpenSubsection(): void
    {
        $process = (new Process())
            ->startSection('Main')
            ->startSubsection('A')
            ->startSubsection('A1', false)
            ->endSection()
            ->startSection('Next');

        $this->assertSame(['Total exec time : T', '---- ', 'Main : T', '|- A : T', '|-- A1 : T', '---- ', 'Next : T'], self::tree($process));
    }

    public function testASubsectionNeedsASection(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No section has been started.');

        (new Process())->startSubsection('A');
    }

    public function testASubsectionNeedsASectionStillOpen(): void
    {
        $process = (new Process())->startSection('Main')->endSection();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No section has been started.');

        $process->startSubsection('A');
    }

    public function testGetTimeClosesTheProcessOnce(): void
    {
        $process = new Process();
        usleep(2000);

        $first = $process->getTime();
        usleep(2000);

        $this->assertMatchesRegularExpression('/^\d+ ms $/', $first);
        $this->assertSame($first, $process->getTime(), 'the end time is set once');
        $this->assertSame('Total exec time : ' . $first, $process->toArray()[0]);
    }

    public function testCreateAndTheDeprecatedSingletonShareOneInstance(): void
    {
        $this->assertSame(Process::create(), Process::create());
        $this->assertSame(Process::create(), Process::singleton());
    }
}
