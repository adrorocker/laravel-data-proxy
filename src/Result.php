<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use ArrayAccess;
use JsonSerializable;

/**
 * The final result DTO containing all resolved data
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
final class Result implements Arrayable, Jsonable, JsonSerializable, ArrayAccess
{
    /** @var array<string, mixed> */
    protected array $data;

    /** @var array<string, mixed> */
    protected array $metrics;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metrics
     */
    public function __construct(array $data, array $metrics = [])
    {
        $this->data = $data;
        $this->metrics = $metrics;
    }

    /**
     * Get a value by key
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Magic getter for clean access
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * Magic isset
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * Check if key exists
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Get all data
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get only specified keys
     *
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }

    /**
     * Get all except specified keys
     *
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
    }

    /**
     * Get execution metrics
     *
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        return $this->metrics;
    }

    /**
     * Merge with another result
     */
    public function merge(Result $other): self
    {
        return new self(
            array_merge($this->data, $other->data),
            array_merge_recursive($this->metrics, $other->metrics),
        );
    }

    /**
     * Transform specific values
     *
     * @param array<string, callable(mixed): mixed> $transformers
     */
    public function transform(array $transformers): self
    {
        $data = $this->data;

        foreach ($transformers as $key => $transformer) {
            if (isset($data[$key])) {
                $data[$key] = $transformer($data[$key]);
            }
        }

        return new self($data, $this->metrics);
    }

    /**
     * Map result to a custom DTO class
     *
     * @throws \InvalidArgumentException if class does not exist
     */
    public function mapTo(string $class): object
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Class does not exist: {$class}");
        }

        if (method_exists($class, 'fromResult')) {
            return $class::fromResult($this);
        }

        return new $class(...$this->data);
    }

    /**
     * Get count of data items
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Check if result is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Check if result is not empty
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_map(function ($value) {
            if ($value instanceof DataSet) {
                return $value->toArray();
            }
            if ($value instanceof PaginatedResult) {
                return $value->toArray();
            }
            if ($value instanceof Arrayable) {
                return $value->toArray();
            }
            return $value;
        }, $this->data);
    }

    public function toJson($options = 0): string
    {
        $json = json_encode($this->jsonSerialize(), $options);

        return $json === false ? '{}' : $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Format for API responses with optional metadata
     *
     * @return array<string, mixed>
     */
    public function toResponse(bool $includeMetrics = false): array
    {
        $response = ['data' => $this->toArray()];

        if ($includeMetrics) {
            $response['meta'] = $this->metrics;
        }

        return $response;
    }

    // ArrayAccess implementation

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * @throws \LogicException Result is immutable
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Result is immutable. Use transform() to create a modified copy.');
    }

    /**
     * @throws \LogicException Result is immutable
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Result is immutable. Use transform() to create a modified copy.');
    }
}
