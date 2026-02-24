<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Tests\Unit;

use AdroSoftware\DataProxy\DataSet;
use PHPUnit\Framework\TestCase;

class DataSetTest extends TestCase
{
    public function test_it_can_be_created_from_array(): void
    {
        $dataset = new DataSet([1, 2, 3], 3);
        
        $this->assertInstanceOf(DataSet::class, $dataset);
        $this->assertEquals(3, $dataset->count());
    }

    public function test_it_can_create_empty_dataset(): void
    {
        $dataset = DataSet::empty();
        
        $this->assertTrue($dataset->isEmpty());
        $this->assertEquals(0, $dataset->count());
    }

    public function test_it_can_iterate(): void
    {
        $dataset = new DataSet([1, 2, 3]);
        $result = [];
        
        foreach ($dataset as $item) {
            $result[] = $item;
        }
        
        $this->assertEquals([1, 2, 3], $result);
    }

    public function test_map_is_lazy(): void
    {
        $callCount = 0;
        $dataset = new DataSet([1, 2, 3]);
        
        $mapped = $dataset->map(function ($item) use (&$callCount) {
            $callCount++;
            return $item * 2;
        });
        
        // Map should not execute until iteration
        $this->assertEquals(0, $callCount);
        
        // Now iterate
        $mapped->all();
        $this->assertEquals(3, $callCount);
    }

    public function test_filter_is_lazy(): void
    {
        $dataset = new DataSet([1, 2, 3, 4, 5]);
        
        $filtered = $dataset->filter(fn($item) => $item > 2);
        
        $this->assertEquals([3, 4, 5], array_values($filtered->all()));
    }

    public function test_first_returns_first_item(): void
    {
        $dataset = new DataSet([1, 2, 3]);
        
        $this->assertEquals(1, $dataset->first());
    }

    public function test_first_returns_default_when_empty(): void
    {
        $dataset = DataSet::empty();
        
        $this->assertEquals('default', $dataset->first('default'));
    }

    public function test_last_returns_last_item(): void
    {
        $dataset = new DataSet([1, 2, 3]);
        
        $this->assertEquals(3, $dataset->last());
    }

    public function test_take_limits_results(): void
    {
        $dataset = new DataSet([1, 2, 3, 4, 5]);
        
        $taken = $dataset->take(3);
        
        $this->assertEquals([1, 2, 3], $taken->all());
    }

    public function test_skip_offsets_results(): void
    {
        $dataset = new DataSet([1, 2, 3, 4, 5]);
        
        $skipped = $dataset->skip(2);
        
        $this->assertEquals([3, 4, 5], array_values($skipped->all()));
    }

    public function test_pluck_extracts_field(): void
    {
        $dataset = new DataSet([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        
        $names = $dataset->pluck('name');
        
        $this->assertEquals(['Alice', 'Bob'], $names->all());
    }

    public function test_key_by_indexes_by_field(): void
    {
        $dataset = new DataSet([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        
        $keyed = $dataset->keyBy('id');
        
        $this->assertArrayHasKey(1, $keyed);
        $this->assertArrayHasKey(2, $keyed);
        $this->assertEquals('Alice', $keyed[1]['name']);
    }

    public function test_group_by_groups_items(): void
    {
        $dataset = new DataSet([
            ['type' => 'a', 'value' => 1],
            ['type' => 'b', 'value' => 2],
            ['type' => 'a', 'value' => 3],
        ]);
        
        $grouped = $dataset->groupBy('type');
        
        $this->assertCount(2, $grouped['a']);
        $this->assertCount(1, $grouped['b']);
    }

    public function test_reduce_accumulates_value(): void
    {
        $dataset = new DataSet([1, 2, 3, 4, 5]);
        
        $sum = $dataset->reduce(fn($carry, $item) => $carry + $item, 0);
        
        $this->assertEquals(15, $sum);
    }

    public function test_contains_checks_for_match(): void
    {
        $dataset = new DataSet([1, 2, 3]);
        
        $this->assertTrue($dataset->contains(fn($item) => $item === 2));
        $this->assertFalse($dataset->contains(fn($item) => $item === 5));
    }

    public function test_every_checks_all_match(): void
    {
        $dataset = new DataSet([2, 4, 6]);
        
        $this->assertTrue($dataset->every(fn($item) => $item % 2 === 0));
        $this->assertFalse($dataset->every(fn($item) => $item > 3));
    }

    public function test_chunk_processes_in_batches(): void
    {
        $dataset = new DataSet([1, 2, 3, 4, 5]);
        $chunks = [];
        
        $dataset->chunk(2, function ($chunk, $index) use (&$chunks) {
            $chunks[$index] = $chunk;
        });
        
        $this->assertCount(3, $chunks);
        $this->assertEquals([1, 2], $chunks[0]);
        $this->assertEquals([3, 4], $chunks[1]);
        $this->assertEquals([5], $chunks[2]);
    }

    public function test_to_array_converts_items(): void
    {
        $dataset = new DataSet([1, 2, 3]);
        
        $this->assertEquals([1, 2, 3], $dataset->toArray());
    }

    public function test_json_serialize(): void
    {
        $dataset = new DataSet([1, 2, 3]);
        
        $this->assertEquals('[1,2,3]', json_encode($dataset));
    }
}
