<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Contracts;

/**
 * Interface for cache adapters
 */
interface CacheAdapterInterface
{
    /**
     * Get a value from cache
     */
    public function get(string $key): mixed;

    /**
     * Set a value in cache
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * Check if a key exists in cache
     */
    public function has(string $key): bool;

    /**
     * Remove a key from cache
     */
    public function forget(string $key): void;

    /**
     * Apply cache tags (if supported)
     *
     * @param array<int, string> $tags
     */
    public function tags(array $tags): static;
}
