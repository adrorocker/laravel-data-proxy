# Laravel Data Proxy

A GraphQL-like declarative data retrieval layer for Laravel with automatic query batching and optimization.

## Why DataProxy?

| Problem | DataProxy Solution |
|---------|-------------------|
| N+1 queries across multiple data sources | Automatic query batching by model |
| Verbose, scattered query code | Declarative requirements in one place |
| Complex eager loading setup | GraphQL-like nested shapes |
| No visibility into query performance | Built-in metrics tracking |
| Repetitive data fetching patterns | Reusable, composable data classes |

## Features

- **Declarative Requirements** - Define what data you need, not how to fetch it
- **Automatic Query Batching** - Same-model lookups are combined into single queries
- **Nested Relations** - GraphQL-like shapes with field selection and constraints
- **Schema Agnostic** - No schema assumptions; respects your Eloquent model configurations
- **Memory Efficient** - Lazy DataSet collections with chunking support
- **Built-in Caching** - Per-requirement caching with tags support
- **Presenter Support** - Integrate with any presenter package
- **Pagination** - First-class pagination handling
- **Metrics Tracking** - Monitor query counts, execution time, and memory usage

## Requirements

- PHP 8.2 or higher
- Laravel 11.x, 12.x, or 13.x

## Installation

```bash
composer require adrosoftware/laravel-data-proxy
```

The package auto-registers its service provider and facade with Laravel.

### Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=dataproxy-config
```

## Quick Start

```php
use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use App\Models\User;
use App\Models\Post;

// Define all your data requirements in one place
$result = DataProxy::make()->fetch(
    Requirements::make()
        // Fetch a single user with their profile and roles
        ->one('user', User::class, $userId,
            Shape::make()
                ->select('id', 'name', 'email')
                ->with('profile')
                ->with('roles', Shape::make()->select('id', 'name'))
        )
        // Fetch their recent published posts
        ->query('posts', Post::class,
            Shape::make()
                ->where('user_id', $userId)
                ->where('published', true)
                ->latest()
                ->limit(10)
                ->with('tags')
        )
        // Get aggregate counts
        ->count('totalPosts', Post::class,
            Shape::make()->where('user_id', $userId)
        )
        // Compute derived values
        ->compute('stats', fn($data) => [
            'posts' => $data['totalPosts'],
            'hasProfile' => $data['user']?->profile !== null,
        ], dependsOn: ['user', 'totalPosts'])
);

// Access your data
echo $result->user->name;
echo $result->user->profile->bio;

foreach ($result->posts as $post) {
    echo $post->title;
}

echo "Total posts: " . $result->totalPosts;
echo "Has profile: " . ($result->stats['hasProfile'] ? 'Yes' : 'No');

// Get performance metrics
$metrics = $result->metrics();
// ['queries' => 3, 'time_ms' => 12.5, 'memory_mb' => 2.1, 'batch_savings' => 2]
```

## Core Concepts

### Requirements

The `Requirements` class defines what data you need:

```php
Requirements::make()
    ->one('user', User::class, $id)           // Single entity by ID
    ->many('users', User::class, [1, 2, 3])   // Multiple entities by IDs
    ->query('posts', Post::class, $shape)     // Query with constraints
    ->paginate('posts', Post::class, 15, 1)   // Paginated query
    ->count('total', Post::class)             // Count aggregate
    ->sum('views', Post::class, 'view_count') // Sum aggregate
    ->avg('rating', Post::class, 'rating')    // Average aggregate
    ->min('oldest', Post::class, 'created_at') // Min aggregate
    ->max('newest', Post::class, 'created_at') // Max aggregate
    ->raw('custom', 'SELECT ...', $bindings)  // Raw SQL
    ->compute('derived', $callback, $deps)    // Computed value
    ->cache('user', 'user:1', ttl: 3600)      // Cache configuration
```

### Shape

The `Shape` class defines the structure of data to retrieve:

```php
Shape::make()
    // Field selection
    ->select('id', 'title', 'content')

    // Relations with nested shapes
    ->with('author')
    ->with('comments', Shape::make()
        ->select('id', 'post_id', 'body') // Include foreign key when selecting fields
        ->with('author')
        ->latest()
        ->limit(5)
    )

    // Constraints
    ->where('status', 'published')
    ->where('views', '>', 100)
    ->whereIn('category_id', [1, 2, 3])
    ->whereNull('deleted_at')
    ->whereHas('comments')

    // Ordering and pagination
    ->orderBy('created_at', 'desc')
    ->latest()  // Shorthand for orderBy('created_at', 'desc')
    ->limit(10)
    ->offset(20)

    // Custom query scopes (accumulate - multiple calls are supported)
    ->scope(fn($query) => $query->withCount('likes'))
    ->scope(fn($query, $resolved) => $query->whereIn('author_id', $resolved['followedIds']))

    // Output format
    ->asArray()  // Return arrays instead of models
    ->present(PostPresenter::class)  // Apply presenter
```

### Custom Query Scopes

Apply custom query modifications using scopes. Multiple scopes accumulate and are applied in order:

```php
Shape::make()
    // First scope - add aggregates
    ->scope(fn($query) => $query->withCount('likes'))

    // Second scope - add visibility constraints
    ->scope(fn($query, $resolved) => $query->whereIn('author_id', $resolved['followedIds']))

    // Conditional scope using when()
    ->when($excludeIds, fn($shape) => $shape->scope(
        fn($query) => $query->whereNotIn('id', $excludeIds)
    ))
```

Scopes receive `($query, $resolved)` parameters where `$resolved` contains all previously resolved requirements.

To inspect or clear scopes:
```php
$shape->getScopes();    // Returns array of callables
$shape->clearScopes();  // Removes all scopes
```

### Result

The `Result` class provides access to resolved data:

```php
// Multiple access patterns
$result->user;           // Magic getter
$result->get('user');    // Method access
$result['user'];         // Array access

// Check existence
$result->has('user');

// Get subsets
$result->all();
$result->only(['user', 'posts']);
$result->except(['metrics']);

// Transform values
$result->transform([
    'posts' => fn($posts) => $posts->take(5),
]);

// For API responses
return response()->json($result->toResponse());
// { "data": { "user": {...}, "posts": [...] } }

return response()->json($result->toResponse(includeMetrics: true));
// { "data": {...}, "meta": { "queries": 3, "time_ms": 12.5 } }
```

## Using the Facade

```php
use AdroSoftware\DataProxy\Laravel\DataProxyFacade as Data;

$result = Data::fetch(
    Requirements::make()
        ->one('user', User::class, 1)
);

// Or with inline builder
$result = Data::query(function ($r) {
    $r->one('user', User::class, auth()->id())
      ->query('posts', Post::class, Shape::make()->limit(10));
});
```

## Configuration Presets

```php
// For API use (caching enabled, metrics enabled)
$result = DataProxy::forApi()->fetch($requirements);

// For large exports (no caching, larger chunks, metrics disabled)
$result = DataProxy::forExport()->fetch($requirements);

// For high performance (aggressive caching, metrics disabled)
$result = DataProxy::forPerformance()->fetch($requirements);

// Custom configuration
$result = DataProxy::make()
    ->configure([
        'cache.ttl' => 7200,
        'metrics.enabled' => false,
    ])
    ->fetch($requirements);
```

## Creating Data Classes

Organize your data requirements into reusable classes:

```php
namespace App\Data;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use AdroSoftware\DataProxy\Result;
use App\Models\User;
use App\Models\Post;

class DashboardData
{
    public static function fetch(int $userId): Result
    {
        return DataProxy::make()->fetch(
            Requirements::make()
                ->one('user', User::class, $userId, self::userShape())
                ->query('recentPosts', Post::class, self::recentPostsShape($userId))
                ->count('totalPosts', Post::class, Shape::make()->where('user_id', $userId))
                ->compute('stats', fn($d) => [
                    'posts' => $d['totalPosts'],
                    'hasRecentActivity' => $d['recentPosts']->isNotEmpty(),
                ], ['totalPosts', 'recentPosts'])
        );
    }

    protected static function userShape(): Shape
    {
        return Shape::make()
            ->select('id', 'name', 'email', 'avatar')
            ->with('profile')
            ->with('roles', Shape::make()->select('id', 'name'));
    }

    protected static function recentPostsShape(int $userId): Shape
    {
        return Shape::make()
            ->where('user_id', $userId)
            ->where('published', true)
            ->latest()
            ->limit(5)
            ->with('tags');
    }
}

// Usage
$data = DashboardData::fetch(auth()->id());
return view('dashboard', $data->all());
```

## Working with DataSet

Query results are returned as lazy `DataSet` collections:

```php
$result->posts->each(fn($post) => echo $post->title);
$result->posts->map(fn($post) => $post->title);
$result->posts->filter(fn($post) => $post->views > 100);
$result->posts->pluck('title');
$result->posts->keyBy('id');
$result->posts->groupBy('category_id');
$result->posts->first();
$result->posts->count();
$result->posts->isEmpty();

// Memory-efficient chunking
$result->posts->chunk(100, function ($chunk) {
    // Process chunk
});

// Convert to other formats
$result->posts->all();      // Array
$result->posts->toArray();  // Nested array
$result->posts->collect();  // Laravel Collection
```

## Pagination

```php
$result = DataProxy::make()->fetch(
    Requirements::make()
        ->paginate('posts', Post::class, perPage: 15, page: 1,
            shape: Shape::make()->where('published', true)->latest()
        )
);

foreach ($result->posts as $post) {
    echo $post->title;
}

echo "Page " . $result->posts->currentPage();
echo " of " . $result->posts->lastPage();
echo " - Total: " . $result->posts->total();

if ($result->posts->hasMorePages()) {
    // Show next page link
}
```

## Caching

```php
$result = DataProxy::make()->fetch(
    Requirements::make()
        ->query('categories', Category::class, Shape::make()->where('active', true))
        ->cache('categories', 'categories:active', ttl: 3600, tags: ['categories'])
);

// The categories query will be cached for 1 hour
// Invalidate with: Cache::tags(['categories'])->flush()
```

## Presenter Integration

### Using a Closure Adapter

```php
use AdroSoftware\DataProxy\Adapters\ClosurePresenterAdapter;

$adapter = new ClosurePresenterAdapter();
$adapter->register(User::class, function ($user) {
    return new class($user) {
        public function __construct(private $user) {}
        public function __get($name) { return $this->user->{$name}; }
        public function fullName(): string {
            return $this->user->first_name . ' ' . $this->user->last_name;
        }
    };
});

$result = DataProxy::make()
    ->withPresenter($adapter)
    ->fetch($requirements);
```

### Using Laravel Model Presenter

```php
use AdroSoftware\DataProxy\Adapters\LaravelModelPresenterAdapter;

$adapter = new LaravelModelPresenterAdapter(
    namespace: 'App\\Presenters\\',
    suffix: 'Presenter'
);

$result = DataProxy::make()
    ->withPresenter($adapter)
    ->fetch(
        Requirements::make()
            ->one('user', User::class, 1,
                Shape::make()->present(UserPresenter::class)
            )
    );

// Presenter methods available
echo $result->user->fullName();
```

## Documentation

For detailed documentation, see the `/docs` directory:

- [Getting Started](docs/getting-started.md)
- [Usage Guide](docs/usage.md)
- [Use Cases](docs/use-cases.md)
- [Configuration](docs/configuration.md)
- [API Reference](docs/api-reference.md)

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
