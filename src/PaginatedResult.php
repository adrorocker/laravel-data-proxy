<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use Traversable;
use Countable;
use JsonSerializable;

/**
 * Wrapper for paginated results
 */
class PaginatedResult implements Arrayable, JsonSerializable, IteratorAggregate, Countable
{
    protected LengthAwarePaginator $paginator;

    public function __construct(LengthAwarePaginator $paginator)
    {
        $this->paginator = $paginator;
    }

    /**
     * Get items as DataSet
     */
    public function items(): DataSet
    {
        return new DataSet($this->paginator->items(), $this->paginator->count());
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
     */
    public function getPaginator(): LengthAwarePaginator
    {
        return $this->paginator;
    }

    public function getIterator(): Traversable
    {
        return $this->items()->getIterator();
    }

    public function count(): int
    {
        return $this->paginator->count();
    }

    public function toArray(): array
    {
        return [
            'data' => collect($this->paginator->items())->map(
                fn($item) => $item instanceof Arrayable ? $item->toArray() : $item
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

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
