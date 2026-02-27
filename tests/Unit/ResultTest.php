<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Tests\Unit;

use AdroSoftware\DataProxy\Result;
use AdroSoftware\DataProxy\DataSet;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function test_it_can_be_created(): void
    {
        $result = new Result(['user' => 'John']);

        $this->assertInstanceOf(Result::class, $result);
    }

    public function test_it_can_get_values_by_key(): void
    {
        $result = new Result(['user' => 'John', 'age' => 30]);

        $this->assertEquals('John', $result->get('user'));
        $this->assertEquals(30, $result->get('age'));
    }

    public function test_it_returns_default_for_missing_key(): void
    {
        $result = new Result([]);

        $this->assertEquals('default', $result->get('missing', 'default'));
    }

    public function test_it_supports_magic_getter(): void
    {
        $result = new Result(['user' => 'John']);

        $this->assertEquals('John', $result->user);
    }

    public function test_it_supports_array_access(): void
    {
        $result = new Result(['user' => 'John']);

        $this->assertEquals('John', $result['user']);
        $this->assertTrue(isset($result['user']));
        $this->assertFalse(isset($result['missing']));
    }

    public function test_has_checks_key_existence(): void
    {
        $result = new Result(['user' => 'John']);

        $this->assertTrue($result->has('user'));
        $this->assertFalse($result->has('missing'));
    }

    public function test_all_returns_all_data(): void
    {
        $data = ['user' => 'John', 'age' => 30];
        $result = new Result($data);

        $this->assertEquals($data, $result->all());
    }

    public function test_only_returns_specified_keys(): void
    {
        $result = new Result(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertEquals(['a' => 1, 'c' => 3], $result->only(['a', 'c']));
    }

    public function test_except_excludes_specified_keys(): void
    {
        $result = new Result(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertEquals(['a' => 1, 'c' => 3], $result->except(['b']));
    }

    public function test_metrics_returns_metrics(): void
    {
        $metrics = ['queries' => 5, 'time_ms' => 10.5];
        $result = new Result([], $metrics);

        $this->assertEquals($metrics, $result->metrics());
    }

    public function test_merge_combines_results(): void
    {
        $result1 = new Result(['a' => 1]);
        $result2 = new Result(['b' => 2]);

        $merged = $result1->merge($result2);

        $this->assertEquals(['a' => 1, 'b' => 2], $merged->all());
    }

    public function test_transform_applies_transformers(): void
    {
        $result = new Result(['count' => 5]);

        $transformed = $result->transform([
            'count' => fn($v) => $v * 2,
        ]);

        $this->assertEquals(10, $transformed->get('count'));
    }

    public function test_to_array_converts_nested_datasets(): void
    {
        $result = new Result([
            'items' => new DataSet([1, 2, 3]),
        ]);

        $array = $result->toArray();

        $this->assertEquals([1, 2, 3], $array['items']);
    }

    public function test_to_response_formats_for_api(): void
    {
        $result = new Result(['user' => 'John'], ['queries' => 1]);

        $response = $result->toResponse();
        $this->assertEquals(['data' => ['user' => 'John']], $response);

        $responseWithMeta = $result->toResponse(includeMetrics: true);
        $this->assertEquals([
            'data' => ['user' => 'John'],
            'meta' => ['queries' => 1],
        ], $responseWithMeta);
    }

    public function test_json_serialization(): void
    {
        $result = new Result(['user' => 'John']);

        $this->assertEquals('{"user":"John"}', $result->toJson());
        $this->assertEquals('{"user":"John"}', json_encode($result));
    }

    public function test_is_empty_checks_data(): void
    {
        $emptyResult = new Result([]);
        $nonEmptyResult = new Result(['user' => 'John']);

        $this->assertTrue($emptyResult->isEmpty());
        $this->assertFalse($nonEmptyResult->isEmpty());
        $this->assertTrue($nonEmptyResult->isNotEmpty());
    }

    public function test_result_is_immutable_via_offset_set(): void
    {
        $result = new Result(['user' => 'John']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Result is immutable');

        $result['user'] = 'Jane';
    }

    public function test_result_is_immutable_via_offset_unset(): void
    {
        $result = new Result(['user' => 'John']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Result is immutable');

        unset($result['user']);
    }

    public function test_map_to_validates_class_exists(): void
    {
        $result = new Result(['name' => 'John']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class does not exist');

        $result->mapTo('NonExistentClass');
    }
}
