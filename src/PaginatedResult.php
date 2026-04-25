<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\LengthAwarePaginator;
use IteratorAggregate;
use Traversable;
use Countable;
use JsonSerializable;

/**
 * Wrapper for paginated results
 *
 * @template TValue
 * @implements IteratorAggregate<int, TValue>
 * @implements Arrayable<string, mixed>
 */
final class PaginatedResult implements Arrayable, JsonSerializable, IteratorAggregate, Countable
{
    /** @var LengthAwarePaginator<int, TValue> */
    protected LengthAwarePaginator $paginator;

    /** @var array<int, TValue>|null */
    private ?array $cachedItems = null;

    /** @var DataSet<int, TValue>|null */
    private ?DataSet $cachedDataSet = null;

    /**
     * @param LengthAwarePaginator<int, TValue> $paginator
     */
    public function __construct(LengthAwarePaginator $paginator)
    {
        $this->paginator = $paginator;
    }

    /**
     * Get items as DataSet
     *
     * @return DataSet<int, TValue>
     */
    public function items(): DataSet
    {
        if ($this->cachedDataSet !== null) {
            return $this->cachedDataSet;
        }

        $this->cachedItems ??= $this->paginator->items();

        /** @var DataSet<int, TValue> $ds */
        $ds = new DataSet($this->cachedItems, count($this->cachedItems));

        return $this->cachedDataSet = $ds;
    }

    /**
     * Get total count across all pages
     */
    public function total(): int
    {
        return $this->paginator->total();
    }

    /**
     * Get items per page
     */
    public function perPage(): int
    {
        return $this->paginator->perPage();
    }

    /**
     * Get current page number
     */
    public function currentPage(): int
    {
        return $this->paginator->currentPage();
    }

    /**
     * Get last page number
     */
    public function lastPage(): int
    {
        return $this->paginator->lastPage();
    }

    /**
     * Check if there are more pages
     */
    public function hasMorePages(): bool
    {
        return $this->paginator->hasMorePages();
    }

    /**
     * Check if on first page
     */
    public function onFirstPage(): bool
    {
        return $this->paginator->onFirstPage();
    }

    /**
     * Check if on last page
     */
    public function onLastPage(): bool
    {
        return $this->currentPage() === $this->lastPage();
    }

    /**
     * Get the underlying paginator
     *
     * @return LengthAwarePaginator<int, TValue>
     */
    public function getPaginator(): LengthAwarePaginator
    {
        return $this->paginator;
    }

    /**
     * @return Traversable<int, TValue>
     */
    public function getIterator(): Traversable
    {
        return $this->items()->getIterator();
    }

    public function count(): int
    {
        return count($this->cachedItems ??= $this->paginator->items());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $items = $this->cachedItems ??= $this->paginator->items();

        return [
            'data' => collect($items)->map(
                fn($item) => $item instanceof Arrayable ? $item->toArray() : $item,
            )->all(),
            'pagination' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'has_more' => $this->hasMorePages(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
