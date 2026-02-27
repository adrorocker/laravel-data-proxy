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
 *
 * @template TKey of array-key
 * @template TValue
 * @implements IteratorAggregate<TKey, TValue>
 * @implements Arrayable<TKey, TValue>
 */
final class DataSet implements IteratorAggregate, Countable, Arrayable, JsonSerializable
{
    /** @var iterable<TKey, TValue> */
    protected iterable $source;
    protected ?int $count;

    /** @var array<TKey, TValue>|null */
    protected ?array $materialized = null;

    /** @var array<int, callable(TValue, TKey): (TValue|false)> */
    protected array $pipes = [];

    /**
     * @param iterable<TKey, TValue> $source
     */
    public function __construct(iterable $source, ?int $count = null)
    {
        $this->source = $source;
        $this->count = $count;
    }

    /**
     * @return self<int, mixed>
     */
    public static function empty(): self
    {
        return new self([], 0);
    }

    /**
     * @template TNewKey of array-key
     * @template TNewValue
     * @param iterable<TNewKey, TNewValue> $source
     * @return self<TNewKey, TNewValue>
     */
    public static function from(iterable $source, ?int $count = null): self
    {
        if ($source instanceof Collection) {
            /** @var self<TNewKey, TNewValue> */
            return new self($source, $source->count());
        }
        /** @var self<TNewKey, TNewValue> */
        return new self($source, $count);
    }

    /**
     * @return Traversable<TKey, TValue>
     */
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
     *
     * @return self<TKey, TValue>
     */
    public function map(callable $callback): self
    {
        $clone = clone $this;
        $clone->pipes[] = fn($item, $key) => $callback($item, $key);
        $clone->materialized = null;
        return $clone;
    }

    /**
     * Lazy filter - doesn't execute until iteration
     *
     * @return self<TKey, TValue>
     */
    public function filter(?callable $callback = null): self
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
     *
     * @return self<TKey, TValue>
     */
    public function take(int $limit): self
    {
        /** @var \Generator<TKey, TValue> $generator */
        $generator = (function () use ($limit) {
            $count = 0;
            foreach ($this as $key => $item) {
                if ($count >= $limit) {
                    break;
                }
                yield $key => $item;
                $count++;
            }
        })();

        return new self($generator, min($limit, $this->count ?? PHP_INT_MAX));
    }

    /**
     * Skip first N items
     *
     * @return self<TKey, TValue>
     */
    public function skip(int $offset): self
    {
        /** @var \Generator<TKey, TValue> $generator */
        $generator = (function () use ($offset) {
            $count = 0;
            foreach ($this as $key => $item) {
                if ($count++ >= $offset) {
                    yield $key => $item;
                }
            }
        })();

        return new self($generator, $this->count !== null ? max(0, $this->count - $offset) : null);
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
     *
     * @return self<array-key, mixed>
     */
    public function pluck(string $field, ?string $keyBy = null): self
    {
        /** @var \Generator<array-key, mixed> $generator */
        $generator = (function () use ($field, $keyBy) {
            foreach ($this as $item) {
                $value = data_get($item, $field);
                if ($keyBy !== null) {
                    yield data_get($item, $keyBy) => $value;
                } else {
                    yield $value;
                }
            }
        })();

        return new self($generator, $this->count);
    }

    /**
     * Key by field
     *
     * @param string|callable(TValue): array-key $key
     * @return array<array-key, TValue>
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
     *
     * @param string|callable(TValue): array-key $key
     * @return array<array-key, array<int, TValue>>
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
     *
     * @return self<TKey, TValue>
     */
    public function each(callable $callback): self
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
     *
     * @return array<int, TValue>
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
     *
     * @return Collection<int, TValue>
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
     *
     * @return int<0, max>
     */
    public function count(): int
    {
        if ($this->count !== null) {
            return max(0, $this->count);
        }

        // Cache the computed count to avoid repeated iteration
        // This is an acceptable side effect for performance
        $this->count = iterator_count($this->getIteratorForCounting());

        return max(0, $this->count);
    }

    /**
     * Get a fresh iterator for counting (avoids consuming the main iterator)
     *
     * @return \Traversable<TKey, TValue>
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

    /**
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        return array_map(
            fn($item) => $item instanceof Arrayable ? $item->toArray() : $item,
            $this->all(),
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
