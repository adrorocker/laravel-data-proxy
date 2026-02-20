<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use Traversable;
use Countable;
use JsonSerializable;

/**
 * Memory-efficient, lazy data collection
 */
class DataSet implements IteratorAggregate, Countable, Arrayable, JsonSerializable
{
    protected iterable $source;
    protected ?int $count;
    protected ?array $materialized = null;
    protected array $pipes = [];

    public function __construct(iterable $source, ?int $count = null)
    {
        $this->source = $source;
        $this->count = $count;
    }

    public static function empty(): static
    {
        return new static([], 0);
    }

    public static function from(iterable $source, ?int $count = null): static
    {
        if ($source instanceof Collection) {
            return new static($source, $source->count());
        }
        return new static($source, $count);
    }

    public function getIterator(): Traversable
    {
        $source = $this->materialized ?? $this->source;

        foreach ($source as $key => $item) {
            $result = $item;
            foreach ($this->pipes as $pipe) {
                $result = $pipe($result, $key);
                if ($result === false) {
                    continue 2;
                }
            }
            yield $key => $result;
        }
    }

    /**
     * Lazy map - doesn't execute until iteration
     */
    public function map(callable $callback): static
    {
        $clone = clone $this;
        $clone->pipes[] = fn($item, $key) => $callback($item, $key);
        $clone->materialized = null;
        return $clone;
    }

    /**
     * Lazy filter - doesn't execute until iteration
     */
    public function filter(?callable $callback = null): static
    {
        $clone = clone $this;
        $clone->pipes[] = function ($item) use ($callback) {
            $pass = $callback ? $callback($item) : (bool) $item;
            return $pass ? $item : false;
        };
        $clone->materialized = null;
        $clone->count = null;
        return $clone;
    }

    /**
     * Take first N items
     */
    public function take(int $limit): static
    {
        return new static((function () use ($limit) {
            $count = 0;
            foreach ($this as $key => $item) {
                if ($count >= $limit) break;
                yield $key => $item;
                $count++;
            }
        })(), min($limit, $this->count ?? PHP_INT_MAX));
    }

    /**
     * Skip first N items
     */
    public function skip(int $offset): static
    {
        return new static((function () use ($offset) {
            $count = 0;
            foreach ($this as $key => $item) {
                if ($count++ >= $offset) {
                    yield $key => $item;
                }
            }
        })(), $this->count !== null ? max(0, $this->count - $offset) : null);
    }

    /**
     * Get first item
     */
    public function first(mixed $default = null): mixed
    {
        foreach ($this as $item) {
            return $item;
        }
        return $default;
    }

    /**
     * Get last item (requires full iteration)
     */
    public function last(mixed $default = null): mixed
    {
        $last = $default;
        foreach ($this as $item) {
            $last = $item;
        }
        return $last;
    }

    /**
     * Find by callback
     */
    public function find(callable $callback, mixed $default = null): mixed
    {
        foreach ($this as $item) {
            if ($callback($item)) {
                return $item;
            }
        }
        return $default;
    }

    /**
     * Pluck a field from items
     */
    public function pluck(string $field, ?string $keyBy = null): static
    {
        return new static((function () use ($field, $keyBy) {
            foreach ($this as $item) {
                $value = data_get($item, $field);
                if ($keyBy !== null) {
                    yield data_get($item, $keyBy) => $value;
                } else {
                    yield $value;
                }
            }
        })(), $this->count);
    }

    /**
     * Key by field
     */
    public function keyBy(string|callable $key): array
    {
        $result = [];
        $resolver = is_callable($key) ? $key : fn($item) => data_get($item, $key);

        foreach ($this as $item) {
            $result[$resolver($item)] = $item;
        }

        return $result;
    }

    /**
     * Group by field
     */
    public function groupBy(string|callable $key): array
    {
        $groups = [];
        $resolver = is_callable($key) ? $key : fn($item) => data_get($item, $key);

        foreach ($this as $item) {
            $groupKey = $resolver($item);
            $groups[$groupKey] ??= [];
            $groups[$groupKey][] = $item;
        }

        return $groups;
    }

    /**
     * Reduce to single value
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $result = $initial;
        foreach ($this as $item) {
            $result = $callback($result, $item);
        }
        return $result;
    }

    /**
     * Iterate with callback
     */
    public function each(callable $callback): static
    {
        foreach ($this as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        return $this;
    }

    /**
     * Process in chunks for memory efficiency
     */
    public function chunk(int $size, callable $callback): void
    {
        $chunk = [];
        $chunkIndex = 0;

        foreach ($this as $item) {
            $chunk[] = $item;

            if (count($chunk) >= $size) {
                $callback($chunk, $chunkIndex++);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            $callback($chunk, $chunkIndex);
        }
    }

    /**
     * Check if any item matches
     */
    public function contains(callable $callback): bool
    {
        foreach ($this as $item) {
            if ($callback($item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if all items match
     */
    public function every(callable $callback): bool
    {
        foreach ($this as $item) {
            if (!$callback($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Materialize to array
     */
    public function all(): array
    {
        if ($this->materialized !== null && empty($this->pipes)) {
            return $this->materialized;
        }

        return iterator_to_array($this, false);
    }

    /**
     * Get as Laravel Collection
     */
    public function collect(): Collection
    {
        return collect($this->all());
    }

    /**
     * Count items (may require iteration if unknown)
     *
     * Note: If count was not provided at construction and must be computed,
     * the computed count is cached to avoid repeated iteration.
     */
    public function count(): int
    {
        if ($this->count !== null) {
            return $this->count;
        }

        // Cache the computed count to avoid repeated iteration
        // This is an acceptable side effect for performance
        $this->count = iterator_count($this->getIteratorForCounting());
        return $this->count;
    }

    /**
     * Get a fresh iterator for counting (avoids consuming the main iterator)
     */
    private function getIteratorForCounting(): \Traversable
    {
        $source = $this->materialized ?? $this->source;

        foreach ($source as $key => $item) {
            $result = $item;
            foreach ($this->pipes as $pipe) {
                $result = $pipe($result, $key);
                if ($result === false) {
                    continue 2;
                }
            }
            yield $key => $result;
        }
    }

    public function isEmpty(): bool
    {
        foreach ($this as $item) {
            return false;
        }
        return true;
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function toArray(): array
    {
        return array_map(
            fn($item) => $item instanceof Arrayable ? $item->toArray() : $item,
            $this->all()
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
