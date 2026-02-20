<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

/**
 * Package configuration
 */
class Config
{
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

    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_replace_recursive(static::$defaults, $config);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, mixed $value): static
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                $config[$k] ??= [];
                $config = &$config[$k];
            }
        }

        return $this;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function merge(array $config): static
    {
        $this->config = array_replace_recursive($this->config, $config);
        return $this;
    }
}
