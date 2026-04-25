<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

/**
 * Package configuration
 */
final class Config
{
    /** @var array<string, mixed> */
    protected static array $defaults = [
        'cache' => [
            'enabled' => true,
            'prefix' => 'data_proxy:',
            'ttl' => 3600,
            'store' => null,
        ],
        'query' => [
            'chunk_size' => 1000,
            'max_eager_load_depth' => 5,
            'timeout' => null,
            'merge_shared_eager_loads' => false,
        ],
        'memory' => [
            'max_mb' => 128,
            'gc_threshold' => 0.8,
        ],
        'metrics' => [
            'enabled' => true,
            'detailed' => false,
        ],
        'presenter' => [
            'enabled' => false,
            'adapter' => null,
            'auto_discover' => false,
            'namespace' => 'App\\Presenters\\',
            'suffix' => 'Presenter',
        ],
    ];

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * Memoized resolutions for dot-notation keys to avoid re-walking
     * the nested config array on every `get()`.
     *
     * @var array<string, mixed>
     */
    protected array $resolved = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_replace_recursive(static::$defaults, $config);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        $value = $this->config;
        foreach (explode('.', $key) as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $this->resolved[$key] = $value;
    }

    public function set(string $key, mixed $value): self
    {
        $keys = explode('.', $key);
        /** @var array<string, mixed> $config */
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                /** @var array<string, mixed> $config */
                $config = &$config[$k];
            }
        }

        $this->resolved = [];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function merge(array $config): self
    {
        $this->config = array_replace_recursive($this->config, $config);
        $this->resolved = [];
        return $this;
    }
}
