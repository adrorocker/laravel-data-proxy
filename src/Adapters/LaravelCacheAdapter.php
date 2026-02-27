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

    /** @var array<int, string> */
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

    /**
     * @param array<int, string> $tags
     */
    public function tags(array $tags): static
    {
        $clone = clone $this;
        $clone->tags = $tags;
        return $clone;
    }

    /**
     * @return \Illuminate\Contracts\Cache\Repository
     */
    protected function cache(): \Illuminate\Contracts\Cache\Repository
    {
        /** @var \Illuminate\Cache\CacheManager $cacheManager */
        $cacheManager = Cache::getFacadeRoot();

        /** @var \Illuminate\Contracts\Cache\Repository $cache */
        $cache = $this->store ? $cacheManager->store($this->store) : $cacheManager->store();

        if (!empty($this->tags) && method_exists($cacheManager->getStore(), 'tags')) {
            /** @var \Illuminate\Contracts\Cache\Repository */
            return $cacheManager->tags($this->tags);
        }

        return $cache;
    }
}
