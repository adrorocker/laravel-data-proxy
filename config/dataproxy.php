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
        'store' => env('DATAPROXY_CACHE_STORE', null), // null uses default
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
        'timeout' => null, // seconds, null for no timeout
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
        'gc_threshold' => 0.8, // Trigger GC at 80% of max
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
        'adapter' => null, // PresenterAdapterInterface implementation class
        'auto_discover' => false,
        'namespace' => 'App\\Presenters\\',
        'suffix' => 'Presenter',
    ],
];
