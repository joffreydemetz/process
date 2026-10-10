<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Utils\Tests;

use JDZ\Utils\ProcessItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProcessItemTest extends TestCase
{
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

        // usleep() never wakes early, but it can overshoot a lot: the Windows timer
        // tick is ~15.6 ms and a busy machine adds more. Bound the top loosely.
        $this->assertGreaterThanOrEqual($expectedDelay - 0.02, $item->time);
        $this->assertLessThan(0.5, $item->time);
    }

    public static function times(): array
    {
        return [
            'nothing measurable' => [0.0, '0ms'],
            'under half a millisecond' => [0.0004, '0ms'],
            'milliseconds' => [0.05, '50 ms '],
            'a whole second' => [1.0, '1 s 0 ms '],
            'seconds' => [2.5, '2 s 500 ms '],
            'a whole minute' => [60.0, '1 min 0 s 0 ms '],
            'minutes' => [125.5, '2 min 5 s 500 ms '],
            // rounded to the millisecond first: these printed "1000 ms "
            'just under a second' => [0.9996, '1 s 0 ms '],
            'just under a minute' => [59.9996, '1 min 0 s 0 ms '],
            // an item not closed yet has no time (floor(null) was a deprecation)
            'not closed' => [null, '0ms'],
        ];
    }

    #[DataProvider('times')]
    public function testExportFormatsTheTime(?float $time, string $formatted): void
    {
        $item = new ProcessItem('Step');
        $item->time = $time;

        $this->assertSame(['Step : ' . $formatted], $item->export());
    }
}
