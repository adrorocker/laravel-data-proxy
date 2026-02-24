<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Tests\Unit;

use AdroSoftware\DataProxy\DataSet;
use AdroSoftware\DataProxy\PaginatedResult;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\TestCase;

class PaginatedResultTest extends TestCase
{
    protected function createPaginator(
        array $items,
        int $total,
        int $perPage,
        int $currentPage = 1
    ): LengthAwarePaginator {
        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => 'http://example.com']
        );
    }

    public function test_it_can_be_created_from_paginator(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 10, 3);
        $result = new PaginatedResult($paginator);

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function test_it_returns_items_as_dataset(): void
    {
        $paginator = $this->createPaginator(['a', 'b', 'c'], 10, 3);
        $result = new PaginatedResult($paginator);

        $items = $result->items();

        $this->assertInstanceOf(DataSet::class, $items);
        $this->assertCount(3, $items);
    }

    public function test_it_returns_total_count(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 100, 10);
        $result = new PaginatedResult($paginator);

        $this->assertEquals(100, $result->total());
    }

    public function test_it_returns_per_page(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 100, 15);
        $result = new PaginatedResult($paginator);

        $this->assertEquals(15, $result->perPage());
    }

    public function test_it_returns_current_page(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 100, 10, 5);
        $result = new PaginatedResult($paginator);

        $this->assertEquals(5, $result->currentPage());
    }

    public function test_it_returns_last_page(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 100, 10);
        $result = new PaginatedResult($paginator);

        $this->assertEquals(10, $result->lastPage());
    }

    public function test_has_more_pages_returns_true_when_not_on_last(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 100, 10, 1);
        $result = new PaginatedResult($paginator);

        $this->assertTrue($result->hasMorePages());
    }

    public function test_has_more_pages_returns_false_when_on_last(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 30, 10, 3);
        $result = new PaginatedResult($paginator);

        $this->assertFalse($result->hasMorePages());
    }

    public function test_on_first_page_returns_true_when_page_one(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 100, 10, 1);
        $result = new PaginatedResult($paginator);

        $this->assertTrue($result->onFirstPage());
    }

    public function test_on_first_page_returns_false_when_not_page_one(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 100, 10, 2);
        $result = new PaginatedResult($paginator);

        $this->assertFalse($result->onFirstPage());
    }

    public function test_on_last_page_returns_true_when_on_last(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 30, 10, 3);
        $result = new PaginatedResult($paginator);

        $this->assertTrue($result->onLastPage());
    }

    public function test_on_last_page_returns_false_when_not_on_last(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 30, 10, 2);
        $result = new PaginatedResult($paginator);

        $this->assertFalse($result->onLastPage());
    }

    public function test_on_last_page_uses_exact_equality(): void
    {
        // Test that we use === instead of >= (fixes audit issue #10)
        $paginator = $this->createPaginator([1, 2, 3], 30, 10, 2);
        $result = new PaginatedResult($paginator);

        // Page 2 of 3, not on last page
        $this->assertFalse($result->onLastPage());

        // Page 3 of 3, on last page
        $paginator3 = $this->createPaginator([1, 2, 3], 30, 10, 3);
        $result3 = new PaginatedResult($paginator3);
        $this->assertTrue($result3->onLastPage());
    }

    public function test_it_returns_underlying_paginator(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 100, 10);
        $result = new PaginatedResult($paginator);

        $this->assertSame($paginator, $result->getPaginator());
    }

    public function test_it_is_iterable(): void
    {
        $paginator = $this->createPaginator(['a', 'b', 'c'], 10, 3);
        $result = new PaginatedResult($paginator);

        $items = [];
        foreach ($result as $item) {
            $items[] = $item;
        }

        $this->assertEquals(['a', 'b', 'c'], $items);
    }

    public function test_it_is_countable(): void
    {
        $paginator = $this->createPaginator([1, 2, 3, 4, 5], 100, 5);
        $result = new PaginatedResult($paginator);

        $this->assertCount(5, $result);
        $this->assertEquals(5, count($result));
    }

    public function test_to_array_returns_structured_data(): void
    {
        $paginator = $this->createPaginator(['a', 'b'], 20, 5, 2);
        $result = new PaginatedResult($paginator);

        $array = $result->toArray();

        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('pagination', $array);
        $this->assertEquals(['a', 'b'], $array['data']);
        $this->assertEquals(20, $array['pagination']['total']);
        $this->assertEquals(5, $array['pagination']['per_page']);
        $this->assertEquals(2, $array['pagination']['current_page']);
        $this->assertEquals(4, $array['pagination']['last_page']);
        $this->assertTrue($array['pagination']['has_more']);
    }

    public function test_json_serialize_returns_array(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 30, 10);
        $result = new PaginatedResult($paginator);

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('data', $decoded);
        $this->assertArrayHasKey('pagination', $decoded);
    }

    public function test_it_handles_empty_results(): void
    {
        $paginator = $this->createPaginator([], 0, 10);
        $result = new PaginatedResult($paginator);

        $this->assertEquals(0, $result->total());
        $this->assertCount(0, $result);
        $this->assertTrue($result->onFirstPage());
        $this->assertTrue($result->onLastPage());
        $this->assertFalse($result->hasMorePages());
    }

    public function test_it_handles_single_page(): void
    {
        $paginator = $this->createPaginator([1, 2, 3], 3, 10);
        $result = new PaginatedResult($paginator);

        $this->assertEquals(1, $result->lastPage());
        $this->assertTrue($result->onFirstPage());
        $this->assertTrue($result->onLastPage());
        $this->assertFalse($result->hasMorePages());
    }
}
