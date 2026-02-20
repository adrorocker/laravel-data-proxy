<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

use AdroSoftware\DataProxy\Adapters\LaravelCacheAdapter;
use AdroSoftware\DataProxy\Contracts\CacheAdapterInterface;
use AdroSoftware\DataProxy\Contracts\PresenterAdapterInterface;

/**
 * Main entry point - the DataProxy
 * 
 * A GraphQL-like declarative data retrieval layer for Laravel
 * with query batching and optimization.
 */
class DataProxy
{
    protected Config $config;
    protected ?CacheAdapterInterface $cache = null;
    protected ?PresenterAdapterInterface $presenter = null;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? new Config();

        // Auto-configure Laravel cache if enabled
        if ($this->config->get('cache.enabled')) {
            $this->cache = new LaravelCacheAdapter($this->config->get('cache.store'));
        }
    }

    /**
     * Create a new DataProxy instance
     */
    public static function make(?Config $config = null): static
    {
        return new static($config);
    }

    /**
     * Configure for API use (optimized for JSON responses)
     */
    public static function forApi(): static
    {
        return new static(new Config([
            'cache' => ['enabled' => true, 'ttl' => 300],
            'metrics' => ['enabled' => true],
        ]));
    }

    /**
     * Configure for large data exports
     */
    public static function forExport(): static
    {
        return new static(new Config([
            'cache' => ['enabled' => false],
            'query' => ['chunk_size' => 2000],
            'memory' => ['max_mb' => 512],
            'metrics' => ['enabled' => false],
        ]));
    }

    /**
     * Configure for high-performance scenarios
     */
    public static function forPerformance(): static
    {
        return new static(new Config([
            'cache' => ['enabled' => true, 'ttl' => 3600],
            'metrics' => ['enabled' => false],
        ]));
    }

    /**
     * Use a custom cache adapter
     */
    public function withCache(CacheAdapterInterface $cache): static
    {
        $clone = clone $this;
        $clone->cache = $cache;
        $clone->config->set('cache.enabled', true);
        return $clone;
    }

    /**
     * Disable caching
     */
    public function withoutCache(): static
    {
        $clone = clone $this;
        $clone->cache = null;
        $clone->config->set('cache.enabled', false);
        return $clone;
    }

    /**
     * Use a presenter adapter
     */
    public function withPresenter(PresenterAdapterInterface $presenter): static
    {
        $clone = clone $this;
        $clone->presenter = $presenter;
        $clone->config->set('presenter.enabled', true);
        return $clone;
    }

    /**
     * Update configuration
     */
    public function configure(array|callable $config): static
    {
        $clone = clone $this;

        if (is_callable($config)) {
            $config($clone->config);
        } else {
            foreach ($config as $key => $value) {
                $clone->config->set($key, $value);
            }
        }

        return $clone;
    }

    /**
     * Get the current configuration
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Fetch data based on requirements
     */
    public function fetch(Requirements $requirements): Result
    {
        $resolver = new Resolver(
            $requirements,
            $this->config,
            $this->cache,
            $this->presenter
        );

        return $resolver->resolve();
    }

    /**
     * Shorthand: fetch with inline requirements builder
     */
    public function query(callable $builder): Result
    {
        $requirements = Requirements::make();
        $builder($requirements);
        return $this->fetch($requirements);
    }
}
