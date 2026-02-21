<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Adapters;

use AdroSoftware\DataProxy\Contracts\CacheAdapterInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Laravel cache adapter
 */
class LaravelCacheAdapter implements CacheAdapterInterface
{
    protected ?string $store;
    protected array $tags = [];

    public function __construct(?string $store = null)
    {
        $this->store = $store;
    }

    public function get(string $key): mixed
    {
        return $this->cache()->get($key);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        // Use strict comparison - TTL=0 should not be treated as forever
        if ($ttl !== null) {
            $this->cache()->put($key, $value, $ttl);
        } else {
            $this->cache()->forever($key, $value);
        }
    }

    public function has(string $key): bool
    {
        return $this->cache()->has($key);
    }

    public function forget(string $key): void
    {
        $this->cache()->forget($key);
    }

    public function tags(array $tags): static
    {
        $clone = clone $this;
        $clone->tags = $tags;
        return $clone;
    }

    protected function cache()
    {
        $cache = $this->store ? Cache::store($this->store) : Cache::getFacadeRoot();

        if (!empty($this->tags) && method_exists($cache->getStore(), 'tags')) {
            return $cache->tags($this->tags);
        }

        return $cache;
    }
}
