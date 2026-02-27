# Configuration

This guide covers all configuration options for Laravel Data Proxy.

## Publishing Configuration

```bash
php artisan vendor:publish --tag=dataproxy-config
```

This creates `config/dataproxy.php`.

## Configuration File

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for DataProxy. When enabled, results can be
    | cached per-requirement using the cache() method on Requirements.
    |
    */
    'cache' => [
        'enabled' => env('DATAPROXY_CACHE_ENABLED', true),
        'prefix' => env('DATAPROXY_CACHE_PREFIX', 'dataproxy:'),
        'ttl' => env('DATAPROXY_CACHE_TTL', 3600),
        'store' => env('DATAPROXY_CACHE_STORE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Configuration
    |--------------------------------------------------------------------------
    |
    | Configure query behavior including chunking for large datasets,
    | eager loading depth limits, and query timeouts.
    |
    */
    'query' => [
        'chunk_size' => 1000,
        'max_eager_load_depth' => 5,
        'timeout' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Configuration
    |--------------------------------------------------------------------------
    |
    | Configure memory management. DataProxy can monitor memory usage and
    | trigger garbage collection when approaching limits.
    |
    */
    'memory' => [
        'max_mb' => 128,
        'gc_threshold' => 0.8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | Configure metrics collection. When enabled, DataProxy tracks query
    | counts, execution time, memory usage, and cache hits.
    |
    */
    'metrics' => [
        'enabled' => env('DATAPROXY_METRICS_ENABLED', true),
        'detailed' => env('DATAPROXY_METRICS_DETAILED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Presenter Configuration
    |--------------------------------------------------------------------------
    |
    | Configure presenter integration. DataProxy supports any presenter
    | package via adapters. Set enabled to true and provide an adapter
    | implementation to use presenters.
    |
    */
    'presenter' => [
        'enabled' => false,
        'adapter' => null,
        'auto_discover' => false,
        'namespace' => 'App\\Presenters\\',
        'suffix' => 'Presenter',
    ],
];
```

## Configuration Options

### Cache

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `true` | Enable/disable caching globally |
| `prefix` | string | `'dataproxy:'` | Prefix for all cache keys |
| `ttl` | int | `3600` | Default cache TTL in seconds |
| `store` | string\|null | `null` | Cache store to use (null = default) |

```php
// .env
DATAPROXY_CACHE_ENABLED=true
DATAPROXY_CACHE_PREFIX=dp:
DATAPROXY_CACHE_TTL=7200
DATAPROXY_CACHE_STORE=redis
```

### Query

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `chunk_size` | int | `1000` | Chunk size for large dataset processing |
| `max_eager_load_depth` | int | `5` | Maximum depth for nested eager loading |
| `timeout` | int\|null | `null` | Query timeout in seconds (null = no timeout) |

```php
// config/dataproxy.php
'query' => [
    'chunk_size' => 2000,        // Larger chunks for exports
    'max_eager_load_depth' => 3, // Limit nesting depth
    'timeout' => 30,             // 30 second timeout
],
```

### Memory

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `max_mb` | int | `128` | Maximum memory usage in MB |
| `gc_threshold` | float | `0.8` | Trigger GC at this percentage of max |

```php
// config/dataproxy.php
'memory' => [
    'max_mb' => 256,        // Allow more memory for exports
    'gc_threshold' => 0.75, // Trigger GC earlier
],
```

### Metrics

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `true` | Enable metrics collection |
| `detailed` | bool | `false` | Include detailed per-query metrics |

```php
// .env
DATAPROXY_METRICS_ENABLED=true
DATAPROXY_METRICS_DETAILED=false
```

When enabled, metrics include:
- `queries` - Number of database queries executed
- `cache_hits` - Number of cache hits
- `batch_savings` - Queries saved by batching
- `time_ms` - Total execution time in milliseconds
- `memory_mb` - Memory used during resolution
- `peak_memory_mb` - Peak memory usage

### Presenter

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `false` | Enable presenter support |
| `adapter` | string\|null | `null` | Presenter adapter class |
| `auto_discover` | bool | `false` | Auto-discover presenters by convention |
| `namespace` | string | `'App\\Presenters\\'` | Presenter namespace for auto-discovery |
| `suffix` | string | `'Presenter'` | Presenter class suffix for auto-discovery |

```php
// config/dataproxy.php
'presenter' => [
    'enabled' => true,
    'adapter' => \AdroSoftware\DataProxy\Adapters\LaravelModelPresenterAdapter::class,
    'auto_discover' => true,
    'namespace' => 'App\\Presenters\\',
    'suffix' => 'Presenter',
],
```

---

## Runtime Configuration

### Configure Method

Override configuration at runtime:

```php
use AdroSoftware\DataProxy\DataProxy;

$result = DataProxy::make()
    ->configure([
        'cache.enabled' => false,
        'cache.ttl' => 7200,
        'metrics.enabled' => true,
        'query.chunk_size' => 500,
    ])
    ->fetch($requirements);
```

With a callback:

```php
$result = DataProxy::make()
    ->configure(function ($config) {
        $config->set('cache.enabled', false);
        $config->set('metrics.detailed', true);
    })
    ->fetch($requirements);
```

### Configuration Presets

Built-in presets for common scenarios:

#### API Preset

```php
DataProxy::forApi()
// Equivalent to:
// cache.enabled = true
// cache.ttl = 300
// metrics.enabled = true
```

Optimized for API responses with short caching and metrics.

#### Export Preset

```php
DataProxy::forExport()
// Equivalent to:
// cache.enabled = false
// query.chunk_size = 2000
// memory.max_mb = 512
// metrics.enabled = false
```

Optimized for large data exports with no caching and higher memory limits.

#### Performance Preset

```php
DataProxy::forPerformance()
// Equivalent to:
// cache.enabled = true
// cache.ttl = 3600
// metrics.enabled = false
```

Maximum caching with no metrics overhead.

### Custom Adapters

#### Cache Adapter

```php
$result = DataProxy::make()
    ->withCache($customCacheAdapter)
    ->fetch($requirements);

// Disable caching
$result = DataProxy::make()
    ->withoutCache()
    ->fetch($requirements);
```

#### Presenter Adapter

```php
use AdroSoftware\DataProxy\Adapters\LaravelModelPresenterAdapter;

$presenter = new LaravelModelPresenterAdapter(
    namespace: 'App\\Presenters\\',
    suffix: 'Presenter'
);

$result = DataProxy::make()
    ->withPresenter($presenter)
    ->fetch($requirements);
```

---

## Environment-Based Configuration

### Development

```env
# .env.local
DATAPROXY_CACHE_ENABLED=false
DATAPROXY_METRICS_ENABLED=true
DATAPROXY_METRICS_DETAILED=true
```

### Production

```env
# .env.production
DATAPROXY_CACHE_ENABLED=true
DATAPROXY_CACHE_TTL=3600
DATAPROXY_CACHE_STORE=redis
DATAPROXY_METRICS_ENABLED=true
DATAPROXY_METRICS_DETAILED=false
```

### Testing

```env
# .env.testing
DATAPROXY_CACHE_ENABLED=false
DATAPROXY_METRICS_ENABLED=false
```

---

## Per-Requirement Caching

Override cache settings per requirement:

```php
Requirements::make()
    // Use global cache settings
    ->query('users', User::class)

    // Custom cache key and TTL
    ->query('categories', Category::class)
    ->cache('categories', 'categories:active', ttl: 7200)

    // With tags for invalidation
    ->query('settings', Setting::class)
    ->cache('settings', 'settings:global', ttl: 86400, tags: ['settings'])
```

Cache invalidation:

```php
// Invalidate by tags
Cache::tags(['settings'])->flush();

// Invalidate specific key (hashed)
Cache::forget('dp_' . hash('sha256', 'dataproxy:settings:global'));
```

---

## Service Provider Configuration

Register a custom DataProxy in a service provider:

```php
namespace App\Providers;

use AdroSoftware\DataProxy\Config;
use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Adapters\LaravelModelPresenterAdapter;
use Illuminate\Support\ServiceProvider;

class DataProxyServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(DataProxy::class, function ($app) {
            $config = new Config($app['config']->get('dataproxy', []));

            $proxy = new DataProxy($config);

            // Always use presenter
            if ($config->get('presenter.enabled')) {
                $proxy = $proxy->withPresenter(
                    new LaravelModelPresenterAdapter(
                        $config->get('presenter.namespace'),
                        $config->get('presenter.suffix')
                    )
                );
            }

            return $proxy;
        });
    }
}
```

Usage:

```php
// Inject configured instance
public function __construct(private DataProxy $proxy) {}

// Or resolve from container
$proxy = app(DataProxy::class);
```

---

## Best Practices

### Development

1. **Disable caching** to see fresh data
2. **Enable detailed metrics** to identify slow queries
3. **Lower chunk sizes** to catch memory issues early

### Production

1. **Enable caching** with appropriate TTLs
2. **Use Redis** for cache store (supports tags)
3. **Disable detailed metrics** to reduce overhead
4. **Set query timeouts** to prevent runaway queries

### Testing

1. **Disable caching** for predictable results
2. **Disable metrics** for faster tests
3. **Use in-memory database** for speed

---

## Next Steps

- [API Reference](api-reference.md) - Complete method signatures
- [Use Cases](use-cases.md) - Real-world examples
