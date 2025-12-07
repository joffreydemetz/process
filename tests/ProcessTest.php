<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Utils\Tests;

use JDZ\Utils\Process;
use JDZ\Utils\ProcessItem;
use PHPUnit\Framework\TestCase;

class ProcessTest extends TestCase
{
    private Process $process;

    protected function setUp(): void
    {
        parent::setUp();
        $this->process = new Process();
    }

    public function testConstructorSetsStartTime(): void
    {
        $beforeTime = microtime(true);
        $process = new Process();
        $afterTime = microtime(true);

        // Use reflection to access private start property
        $reflection = new \ReflectionClass($process);
        $startProperty = $reflection->getProperty('start');
        $startProperty->setAccessible(true);
        $start = $startProperty->getValue($process);

        $this->assertIsFloat($start);
        $this->assertGreaterThanOrEqual($beforeTime, $start);
        $this->assertLessThanOrEqual($afterTime, $start);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $instance1 = Process::singleton();
        $instance2 = Process::singleton();

        $this->assertSame($instance1, $instance2);
    }

    public function testSingletonReturnsProcessInstance(): void
    {
        $instance = Process::singleton();

        $this->assertInstanceOf(Process::class, $instance);
    }

    public function testGetTimeReturnsFormattedString(): void
    {
        $process = new Process();
        usleep(10000); // 10ms

        $time = $process->getTime();

        $this->assertIsString($time);
        $this->assertNotEmpty($time);
    }

    public function testGetTimeClosesProcess(): void
    {
        $process = new Process();
        $process->getTime();

        $reflection = new \ReflectionClass($process);
        $endProperty = $reflection->getProperty('end');
        $endProperty->setAccessible(true);
        $end = $endProperty->getValue($process);

        $this->assertIsFloat($end);
    }

    public function testStartSectionCreatesSection(): void
    {
        $result = $this->process->startSection('Test Section');

        $this->assertSame($this->process, $result);

        $reflection = new \ReflectionClass($this->process);
        $sectionsProperty = $reflection->getProperty('sections');
        $sectionsProperty->setAccessible(true);
        $sections = $sectionsProperty->getValue($this->process);

        $this->assertCount(1, $sections);
        $this->assertInstanceOf(ProcessItem::class, $sections[0]);
        $this->assertEquals('Test Section', $sections[0]->name);
    }

    public function testStartSectionEndsCurrentSection(): void
    {
        $this->process->startSection('Section 1');
        usleep(5000);
        $this->process->startSection('Section 2');

        $reflection = new \ReflectionClass($this->process);
        $sectionsProperty = $reflection->getProperty('sections');
        $sectionsProperty->setAccessible(true);
        $sections = $sectionsProperty->getValue($this->process);

        $this->assertCount(2, $sections);
        $this->assertNotNull($sections[0]->end);
    }

    public function testStartMultipleSections(): void
    {
        $this->process->startSection('Section 1');
        $this->process->startSection('Section 2');
        $this->process->startSection('Section 3');

        $reflection = new \ReflectionClass($this->process);
        $sectionsProperty = $reflection->getProperty('sections');
        $sectionsProperty->setAccessible(true);
        $sections = $sectionsProperty->getValue($this->process);

        $this->assertCount(3, $sections);
    }

    public function testStartSubsectionCreatesSubsection(): void
    {
        $this->process->startSection('Main Section');
        $result = $this->process->startSubsection('Subsection');

        $this->assertSame($this->process, $result);

        $reflection = new \ReflectionClass($this->process);
        $sectionsProperty = $reflection->getProperty('sections');
        $sectionsProperty->setAccessible(true);
        $sections = $sectionsProperty->getValue($this->process);

        $this->assertCount(1, $sections[0]->children);
        $this->assertEquals('Subsection', $sections[0]->children[0]->name);
    }

    public function testStartSubsectionWithoutSectionThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("No section has been started.");

        $this->process->startSubsection('Subsection');
    }

    public function testStartSubsectionEndsPreviousByDefault(): void
    {
        $this->process->startSection('Main Section');
        $this->process->startSubsection('Subsection 1');
        usleep(5000);
        $this->process->startSubsection('Subsection 2');

        $reflection = new \ReflectionClass($this->process);
        $sectionsProperty = $reflection->getProperty('sections');
        $sectionsProperty->setAccessible(true);
        $sections = $sectionsProperty->getValue($this->process);

        $this->assertCount(2, $sections[0]->children);
        $this->assertNotNull($sections[0]->children[0]->end);
    }

    public function testStartSubsectionWithEndPreviousFalse(): void
    {
        $this->process->startSection('Main Section');
        $this->process->startSubsection('Subsection 1', false);
        $this->process->startSubsection('Subsection 2', false);

        $reflection = new \ReflectionClass($this->process);
        $sectionsProperty = $reflection->getProperty('sections');
        $sectionsProperty->setAccessible(true);
        $sections = $sectionsProperty->getValue($this->process);

        // Second subsection should be nested under the first
        $this->assertCount(1, $sections[0]->children);
        $this->assertCount(1, $sections[0]->children[0]->children);
    }

    public function testNestedSubsections(): void
    {
        $this->process->startSection('Main Section');
        $this->process->startSubsection('Level 1', false);
        $this->process->startSubsection('Level 2', false);
        $this->process->startSubsection('Level 3', false);

        $reflection = new \ReflectionClass($this->process);
        $sectionsProperty = $reflection->getProperty('sections');
        $sectionsProperty->setAccessible(true);
        $sections = $sectionsProperty->getValue($this->process);

        $this->assertCount(1, $sections[0]->children);
        $this->assertCount(1, $sections[0]->children[0]->children);
        $this->assertCount(1, $sections[0]->children[0]->children[0]->children);
    }

    public function testEndSubsectionMovesUpOneLevel(): void
    {
        $this->process->startSection('Main Section');
        $this->process->startSubsection('Subsection 1', false);
        $this->process->startSubsection('Subsection 2', false);
        usleep(5000);
        $result = $this->process->endSubsection();

        $this->assertSame($this->process, $result);

        $reflection = new \ReflectionClass($this->process);
        $currentSectionProperty = $reflection->getProperty('currentSection');
        $currentSectionProperty->setAccessible(true);
        $currentSection = $currentSectionProperty->getValue($this->process);

        $this->assertEquals('Subsection 1', $currentSection->name);
    }

    public function testEndSubsectionClosesSubsection(): void
    {
        $this->process->startSection('Main Section');
        $this->process->startSubsection('Subsection', false);
        usleep(5000);
        $this->process->endSubsection();

        $reflection = new \ReflectionClass($this->process);
        $sectionsProperty = $reflection->getProperty('sections');
        $sectionsProperty->setAccessible(true);
        $sections = $sectionsProperty->getValue($this->process);

        $this->assertNotNull($sections[0]->children[0]->end);
    }

    public function testEndSubsectionWithNoParentDoesNothing(): void
    {
        $this->process->startSection('Main Section');
        $result = $this->process->endSubsection();

        $this->assertSame($this->process, $result);
    }

    public function testEndSectionClosesAllSubsections(): void
    {
        $this->process->startSection('Main Section');
        $this->process->startSubsection('Level 1', false);
        $this->process->startSubsection('Level 2', false);
        usleep(5000);
        $result = $this->process->endSection();

        $this->assertSame($this->process, $result);

        $reflection = new \ReflectionClass($this->process);
        $sectionsProperty = $reflection->getProperty('sections');
        $sectionsProperty->setAccessible(true);
        $sections = $sectionsProperty->getValue($this->process);

        $this->assertNotNull($sections[0]->end);
        $this->assertNotNull($sections[0]->children[0]->end);
        $this->assertNotNull($sections[0]->children[0]->children[0]->end);
    }

    public function testEndSectionSetsCurrentSectionToNull(): void
    {
        $this->process->startSection('Main Section');
        $this->process->endSection();

        $reflection = new \ReflectionClass($this->process);
        $currentSectionProperty = $reflection->getProperty('currentSection');
        $currentSectionProperty->setAccessible(true);
        $currentSection = $currentSectionProperty->getValue($this->process);

        $this->assertNull($currentSection);
    }

    public function testEndSectionWithNoCurrentSectionDoesNothing(): void
    {
        $result = $this->process->endSection();

        $this->assertSame($this->process, $result);
    }

    public function testCloseEndsCurrentSection(): void
    {
        $this->process->startSection('Main Section');
        usleep(5000);
        $this->process->close();

        $reflection = new \ReflectionClass($this->process);
        $sectionsProperty = $reflection->getProperty('sections');
        $sectionsProperty->setAccessible(true);
        $sections = $sectionsProperty->getValue($this->process);

        $this->assertNotNull($sections[0]->end);
    }

    public function testCloseSetsEndTimeAndCalculatesTime(): void
    {
        $process = new Process();
        usleep(10000);
        $process->close();

        $reflection = new \ReflectionClass($process);
        $endProperty = $reflection->getProperty('end');
        $endProperty->setAccessible(true);
        $end = $endProperty->getValue($process);

        $timeProperty = $reflection->getProperty('time');
        $timeProperty->setAccessible(true);
        $time = $timeProperty->getValue($process);

        $this->assertIsFloat($end);
        $this->assertIsFloat($time);
        $this->assertGreaterThan(0, $time);
    }

    public function testCloseIsIdempotent(): void
    {
        $process = new Process();
        usleep(10000);
        $process->close();

        $reflection = new \ReflectionClass($process);
        $endProperty = $reflection->getProperty('end');
        $endProperty->setAccessible(true);
        $firstEnd = $endProperty->getValue($process);

        usleep(10000);
        $process->close();

        $secondEnd = $endProperty->getValue($process);

        $this->assertEquals($firstEnd, $secondEnd);
    }

    public function testToArrayReturnsFormattedOutput(): void
    {
        $process = new Process();
        $process->startSection('Main Section');
        $process->startSubsection('Subsection');
        usleep(10000);

        $result = $process->toArray();

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result));
        $this->assertStringContainsString('Total exec time', $result[0]);
    }

    public function testToArrayClosesProcess(): void
    {
        $process = new Process();
        $process->toArray();

        $reflection = new \ReflectionClass($process);
        $endProperty = $reflection->getProperty('end');
        $endProperty->setAccessible(true);
        $end = $endProperty->getValue($process);

        $this->assertIsFloat($end);
    }

    public function testToArrayIncludesSections(): void
    {
        $process = new Process();
        $process->startSection('Section 1');
        $process->endSection();
        $process->startSection('Section 2');
        usleep(10000);

        $result = $process->toArray();

        $this->assertStringContainsString('Section 1', implode(' ', $result));
        $this->assertStringContainsString('Section 2', implode(' ', $result));
    }

    public function testToArrayIncludesSeparator(): void
    {
        $process = new Process();
        $process->startSection('Section 1');
        usleep(5000);

        $result = $process->toArray();

        $this->assertContains('---- ', $result);
    }

    public function testComplexWorkflow(): void
    {
        $process = new Process();

        // Start main section
        $process->startSection('Main Process');
        usleep(5000);

        // Add subsections
        $process->startSubsection('Loading Data');
        usleep(5000);
        $process->endSubsection();

        $process->startSubsection('Processing Data');
        usleep(5000);
        $process->endSubsection();

        // End main section
        $process->endSection();

        // Start another section
        $process->startSection('Cleanup');
        usleep(5000);

        $result = $process->toArray();

        $this->assertIsArray($result);
        $this->assertGreaterThan(5, count($result));
        $this->assertStringContainsString('Main Process', implode(' ', $result));
        $this->assertStringContainsString('Loading Data', implode(' ', $result));
        $this->assertStringContainsString('Processing Data', implode(' ', $result));
        $this->assertStringContainsString('Cleanup', implode(' ', $result));
    }

    public function testFormatTimeWithMinutes(): void
    {
        $process = new Process();

        // Manually set time using reflection
        $reflection = new \ReflectionClass($process);
        $startProperty = $reflection->getProperty('start');
        $startProperty->setAccessible(true);
        $startProperty->setValue($process, microtime(true) - 125.5);

        $time = $process->getTime();

        $this->assertStringContainsString('min', $time);
        $this->assertStringContainsString('2 min', $time);
    }

    public function testFormatTimeWithSecondsOnly(): void
    {
        $process = new Process();

        // Manually set time using reflection
        $reflection = new \ReflectionClass($process);
        $startProperty = $reflection->getProperty('start');
        $startProperty->setAccessible(true);
        $startProperty->setValue($process, microtime(true) - 5.5);

        $time = $process->getTime();

        $this->assertStringContainsString('s', $time);
    }

    public function testFormatTimeWithMillisecondsOnly(): void
    {
        $process = new Process();
        usleep(50000); // 50ms

        $time = $process->getTime();

        $this->assertStringContainsString('ms', $time);
    }

    public function testChainedMethodCalls(): void
    {
        $result = $this->process
            ->startSection('Section')
            ->startSubsection('Subsection')
            ->endSubsection()
            ->endSection();

        $this->assertSame($this->process, $result);
    }

    public function testMultipleSectionsWithSubsections(): void
    {
        $this->process->startSection('Section 1');
        $this->process->startSubsection('Sub 1.1');
        $this->process->startSubsection('Sub 1.2');

        $this->process->startSection('Section 2');
        $this->process->startSubsection('Sub 2.1');

        $reflection = new \ReflectionClass($this->process);
        $sectionsProperty = $reflection->getProperty('sections');
        $sectionsProperty->setAccessible(true);
        $sections = $sectionsProperty->getValue($this->process);

        $this->assertCount(2, $sections);
        $this->assertEquals('Section 1', $sections[0]->name);
        $this->assertEquals('Section 2', $sections[1]->name);
        $this->assertCount(2, $sections[0]->children);
        $this->assertCount(1, $sections[1]->children);
    }
}
