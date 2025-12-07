<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Utils\Tests;

use JDZ\Utils\ProcessItem;
use PHPUnit\Framework\TestCase;

class ProcessItemTest extends TestCase
{
    public function testConstructorSetsNameAndParent(): void
    {
        $item = new ProcessItem('TestItem');

        $this->assertEquals('TestItem', $item->name);
        $this->assertNull($item->parent);
        $this->assertIsFloat($item->start);
        $this->assertNull($item->end);
        $this->assertNull($item->time);
        $this->assertEmpty($item->children);
    }

    public function testConstructorWithParent(): void
    {
        $parent = new ProcessItem('Parent');
        $child = new ProcessItem('Child', $parent);

        $this->assertEquals('Child', $child->name);
        $this->assertSame($parent, $child->parent);
    }

    public function testDefaultConstructorName(): void
    {
        $item = new ProcessItem();

        $this->assertEquals('ROOT', $item->name);
    }

    public function testFindByNameReturnsTrue(): void
    {
        $item = new ProcessItem('TestItem');

        $this->assertTrue($item->findByName('TestItem'));
    }

    public function testFindByNameReturnsFalse(): void
    {
        $item = new ProcessItem('TestItem');

        $this->assertFalse($item->findByName('OtherItem'));
    }

    public function testAddChildAddsChildAndSetsParent(): void
    {
        $parent = new ProcessItem('Parent');
        $child = new ProcessItem('Child');

        $result = $parent->addChild($child);

        $this->assertSame($parent, $result);
        $this->assertCount(1, $parent->children);
        $this->assertSame($child, $parent->children[0]);
        $this->assertSame($parent, $child->parent);
    }

    public function testAddMultipleChildren(): void
    {
        $parent = new ProcessItem('Parent');
        $child1 = new ProcessItem('Child1');
        $child2 = new ProcessItem('Child2');

        $parent->addChild($child1)->addChild($child2);

        $this->assertCount(2, $parent->children);
        $this->assertSame($child1, $parent->children[0]);
        $this->assertSame($child2, $parent->children[1]);
    }

    public function testCloseSetsEndTimeAndCalculatesTime(): void
    {
        $item = new ProcessItem('TestItem');
        usleep(10000); // Sleep for 10ms

        $result = $item->close();

        $this->assertSame($item, $result);
        $this->assertIsFloat($item->end);
        $this->assertIsFloat($item->time);
        $this->assertGreaterThan(0, $item->time);
        $this->assertGreaterThanOrEqual(0.01, $item->time); // At least 10ms
    }

    public function testCloseIsIdempotent(): void
    {
        $item = new ProcessItem('TestItem');
        usleep(10000);

        $item->close();
        $firstEndTime = $item->end;
        $firstTime = $item->time;

        usleep(10000);
        $item->close();

        $this->assertEquals($firstEndTime, $item->end);
        $this->assertEquals($firstTime, $item->time);
    }

    public function testCloseClosesAllChildren(): void
    {
        $parent = new ProcessItem('Parent');
        $child1 = new ProcessItem('Child1');
        $child2 = new ProcessItem('Child2');

        $parent->addChild($child1)->addChild($child2);
        usleep(10000);

        $parent->close();

        $this->assertNotNull($parent->end);
        $this->assertNotNull($child1->end);
        $this->assertNotNull($child2->end);
    }

    public function testExportWithNoChildren(): void
    {
        $item = new ProcessItem('TestItem');
        usleep(10000);
        $item->close();

        $result = $item->export();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertStringContainsString('TestItem', $result[0]);
        $this->assertStringContainsString(':', $result[0]);
    }

    public function testExportWithChildren(): void
    {
        $parent = new ProcessItem('Parent');
        $child = new ProcessItem('Child');
        $parent->addChild($child);
        usleep(10000);
        $parent->close();

        $result = $parent->export();

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));
        $this->assertStringContainsString('Parent', $result[0]);
        $this->assertStringContainsString('Child', $result[1]);
        $this->assertStringContainsString('|-', $result[1]);
    }

    public function testExportWithNestedChildren(): void
    {
        $parent = new ProcessItem('Parent');
        $child = new ProcessItem('Child');
        $grandchild = new ProcessItem('Grandchild');

        $parent->addChild($child);
        $child->addChild($grandchild);
        usleep(10000);
        $parent->close();

        $result = $parent->export();

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(3, count($result));
        $this->assertStringContainsString('Parent', $result[0]);
        $this->assertStringContainsString('Child', $result[1]);
        $this->assertStringContainsString('Grandchild', $result[2]);
        $this->assertStringContainsString('|--', $result[2]);
    }

    public function testExportWithExistingList(): void
    {
        $item = new ProcessItem('TestItem');
        $item->close();

        $existingList = ['Line 1', 'Line 2'];
        $result = $item->export($existingList);

        $this->assertCount(3, $result);
        $this->assertEquals('Line 1', $result[0]);
        $this->assertEquals('Line 2', $result[1]);
        $this->assertStringContainsString('TestItem', $result[2]);
    }

    public function testFormatTimeWithMilliseconds(): void
    {
        $item = new ProcessItem('TestItem');
        usleep(50000); // 50ms
        $item->close();

        $result = $item->export();

        $this->assertStringContainsString('ms', $result[0]);
    }

    public function testFormatTimeWithSeconds(): void
    {
        $item = new ProcessItem('TestItem');
        // Manually set time to 2.5 seconds
        $item->end = $item->start + 2.5;
        $item->time = 2.5;

        $result = $item->export();

        $this->assertStringContainsString('s', $result[0]);
        $this->assertStringContainsString('2 s', $result[0]);
    }

    public function testFormatTimeWithMinutes(): void
    {
        $item = new ProcessItem('TestItem');
        // Manually set time to 125.5 seconds (2 min 5 s 500 ms)
        $item->end = $item->start + 125.5;
        $item->time = 125.5;

        $result = $item->export();

        $this->assertStringContainsString('min', $result[0]);
        $this->assertStringContainsString('2 min', $result[0]);
        $this->assertStringContainsString('5 s', $result[0]);
    }

    public function testFormatTimeWithZeroTime(): void
    {
        $item = new ProcessItem('TestItem');
        // Close immediately
        $item->close();

        // For very small times (< 1ms), should still show something
        $result = $item->export();

        $this->assertStringContainsString('TestItem', $result[0]);
        // The time should be formatted (could be 0ms or very small ms value)
        $this->assertNotEquals('TestItem : ', $result[0]);
    }

    public function testChildParentRelationship(): void
    {
        $parent = new ProcessItem('Parent');
        $child1 = new ProcessItem('Child1');
        $child2 = new ProcessItem('Child2');

        $parent->addChild($child1);
        $child1->addChild($child2);

        $this->assertSame($parent, $child1->parent);
        $this->assertSame($child1, $child2->parent);
        $this->assertCount(1, $parent->children);
        $this->assertCount(1, $child1->children);
        $this->assertEmpty($child2->children);
    }

    public function testStartTimeIsSetOnConstruction(): void
    {
        $beforeTime = microtime(true);
        $item = new ProcessItem('TestItem');
        $afterTime = microtime(true);

        $this->assertGreaterThanOrEqual($beforeTime, $item->start);
        $this->assertLessThanOrEqual($afterTime, $item->start);
    }

    public function testTimeCalculationAccuracy(): void
    {
        $item = new ProcessItem('TestItem');
        $expectedDelay = 0.05; // 50ms
        usleep(50000);
        $item->close();

        // Allow for some margin of error (±20ms)
        $this->assertGreaterThanOrEqual($expectedDelay - 0.02, $item->time);
        $this->assertLessThanOrEqual($expectedDelay + 0.02, $item->time);
    }
}
