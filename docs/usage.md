# Usage Guide

This guide covers the complete API for Laravel Data Proxy.

## Schema Agnostic

DataProxy does not enforce any database schema. It reads your Eloquent model configurations and works with whatever structure you have:

- **Primary Keys**: Reads from your model's `$primaryKey` property
- **Key Types**: Reads from your model's `$keyType` property
- **Foreign Keys**: Reads from your Eloquent relationship definitions
- **Table Names**: Reads from your model's `$table` property

```php
// Your models define the schema - DataProxy just reads it
class Product extends Model
{
    protected $table = 'inventory_products';
    protected $primaryKey = 'sku';
    protected $keyType = 'string';

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_code', 'code');
    }
}

// DataProxy works with your schema as-is
Requirements::make()
    ->one('product', Product::class, 'ABC-123')
    ->many('products', Product::class, ['ABC-123', 'DEF-456']);
```

---

## DataProxy

The main entry point for data retrieval.

### Creating Instances

```php
use AdroSoftware\DataProxy\DataProxy;

// Basic instance
$proxy = DataProxy::make();

// With custom config
$proxy = DataProxy::make(new Config([
    'cache' => ['enabled' => false],
]));

// Configuration presets
$proxy = DataProxy::forApi();         // API-optimized
$proxy = DataProxy::forExport();      // Export-optimized
$proxy = DataProxy::forPerformance(); // Performance-optimized
```

### Configuration Methods

```php
// Set configuration options
$proxy = DataProxy::make()->configure([
    'cache.ttl' => 7200,
    'metrics.enabled' => false,
]);

// Or with a callback
$proxy = DataProxy::make()->configure(function ($config) {
    $config->set('cache.ttl', 7200);
});

// Custom cache adapter
$proxy = DataProxy::make()->withCache($customAdapter);

// Disable caching
$proxy = DataProxy::make()->withoutCache();

// Add presenter support
$proxy = DataProxy::make()->withPresenter($presenterAdapter);
```

### Fetching Data

```php
// With Requirements object
$result = $proxy->fetch($requirements);

// With inline builder
$result = $proxy->query(function ($r) {
    $r->one('user', User::class, 1);
    $r->query('posts', Post::class, Shape::make()->limit(10));
});
```

---

## Requirements

The `Requirements` class defines what data you need to fetch.

### Single Entity

Fetch a single model by its primary key:

```php
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;

Requirements::make()
    ->one('user', User::class, $userId);

// With shape
Requirements::make()
    ->one('user', User::class, $userId,
        Shape::make()->select('id', 'name')->with('profile')
    );

// With callable ID (resolved at fetch time)
Requirements::make()
    ->one('currentUser', User::class, fn() => auth()->id());
```

### Multiple Entities

Fetch multiple models by their IDs:

```php
Requirements::make()
    ->many('users', User::class, [1, 2, 3]);

// With callable IDs
Requirements::make()
    ->many('teamMembers', User::class, fn($resolved) => $resolved['team']->member_ids);
```

### Query

Fetch models matching constraints:

```php
Requirements::make()
    ->query('publishedPosts', Post::class,
        Shape::make()
            ->where('published', true)
            ->whereNotNull('published_at')
            ->latest()
            ->limit(10)
    );
```

### Pagination

Fetch paginated results:

```php
Requirements::make()
    ->paginate('posts', Post::class,
        perPage: 15,
        page: 1,
        shape: Shape::make()->where('published', true)->latest()
    );
```

### Aggregates

#### Count

```php
Requirements::make()
    ->count('totalUsers', User::class);

// With constraints
Requirements::make()
    ->count('activeUsers', User::class,
        Shape::make()->where('active', true)
    );
```

#### Sum

```php
Requirements::make()
    ->sum('totalViews', Post::class, 'view_count');

// With constraints
Requirements::make()
    ->sum('userViews', Post::class, 'view_count',
        Shape::make()->where('user_id', $userId)
    );
```

#### Average

```php
Requirements::make()
    ->avg('avgRating', Product::class, 'rating');
```

#### Min/Max

```php
Requirements::make()
    ->min('oldestPost', Post::class, 'created_at')
    ->max('newestPost', Post::class, 'created_at');
```

### Raw SQL

Execute raw SQL queries:

```php
Requirements::make()
    ->raw('trending', "
        SELECT posts.*, COUNT(views.id) as view_count
        FROM posts
        LEFT JOIN views ON views.post_id = posts.id
        WHERE views.created_at > ?
        GROUP BY posts.id
        ORDER BY view_count DESC
        LIMIT 10
    ", [now()->subDay()]);
```

### Computed Values

Calculate derived values from resolved data:

```php
Requirements::make()
    ->count('totalPosts', Post::class)
    ->sum('totalViews', Post::class, 'view_count')
    ->compute('stats', fn($resolved) => [
        'posts' => $resolved['totalPosts'],
        'views' => $resolved['totalViews'],
        'avgViews' => $resolved['totalPosts'] > 0
            ? round($resolved['totalViews'] / $resolved['totalPosts'])
            : 0,
    ], dependsOn: ['totalPosts', 'totalViews']);
```

Dependencies ensure computed values are resolved in the correct order.

### Caching

Configure caching for specific requirements:

```php
Requirements::make()
    ->query('categories', Category::class)
    ->cache('categories', 'categories:active',
        ttl: 3600,
        tags: ['categories']
    );
```

---

## Shape

The `Shape` class defines the structure of data to retrieve.

### Field Selection

```php
use AdroSoftware\DataProxy\Shape;

// Select specific fields
Shape::make()->select('id', 'name', 'email');

// Select all fields (default)
Shape::make(); // Equivalent to SELECT *
```

### Relations

```php
// Simple relation
Shape::make()->with('profile');

// Multiple relations
Shape::make()
    ->with('profile')
    ->with('roles');

// Array syntax
Shape::make()->with(['profile', 'roles']);

// Nested shape for relation
Shape::make()
    ->with('posts', Shape::make()
        ->select('id', 'user_id', 'title', 'published_at') // Include foreign key!
        ->where('published', true)
        ->latest()
        ->limit(5)
    );

// IMPORTANT: When using select() on relations, include the foreign key column
// For BelongsTo: include the foreign key on the child (e.g., 'user_id' on Post)
// For HasMany/HasOne: include the primary key that the relation references

// Deeply nested
Shape::make()
    ->with('posts', Shape::make()
        ->with('comments', Shape::make()
            ->with('author')
            ->latest()
            ->limit(10)
        )
    );

// Callable for custom query
Shape::make()
    ->with('posts', fn($query) => $query->where('published', true)->limit(5));
```

### Constraints

#### Basic Where

```php
Shape::make()
    ->where('status', 'active')           // = operator
    ->where('views', '>', 100)            // comparison
    ->where('rating', '>=', 4.5);
```

#### Where In/Not In

```php
Shape::make()
    ->whereIn('category_id', [1, 2, 3])
    ->whereNotIn('status', ['draft', 'archived']);

// With callable
Shape::make()
    ->whereIn('id', fn($resolved) => $resolved['userIds']);
```

#### Where Between

```php
Shape::make()
    ->whereBetween('created_at', [$startDate, $endDate]);
```

#### Where Null/Not Null

```php
Shape::make()
    ->whereNull('deleted_at')
    ->whereNotNull('published_at');
```

#### Where Has/Doesn't Have

```php
Shape::make()
    ->whereHas('comments')                    // Has any comments
    ->whereHas('comments', fn($q) =>          // Has approved comments
        $q->where('approved', true)
    )
    ->whereDoesntHave('spam');
```

#### Raw Where

```php
Shape::make()
    ->whereRaw('YEAR(created_at) = ?', [2024]);
```

#### Conditional Constraints

```php
Shape::make()
    ->when($onlyPublished, fn($shape) =>
        $shape->where('published', true)
    )
    ->when($categoryId, fn($shape) =>
        $shape->where('category_id', $categoryId)
    );
```

### Custom Scopes

```php
Shape::make()
    ->scope(function ($query, $resolved) {
        $query->where('user_id', $resolved['user']->id);
    });
```

### Ordering

```php
Shape::make()
    ->orderBy('created_at', 'desc')
    ->orderBy('title', 'asc');

// Shortcuts
Shape::make()->latest();                  // orderBy('created_at', 'desc')
Shape::make()->latest('published_at');    // orderBy('published_at', 'desc')
Shape::make()->oldest();                  // orderBy('created_at', 'asc')
```

### Limit and Offset

```php
Shape::make()
    ->limit(10)
    ->offset(20);

// Aliases
Shape::make()
    ->take(10)
    ->skip(20);
```

### Output Format

```php
// Return as arrays instead of models
Shape::make()->asArray();

// Apply a presenter
Shape::make()->present(UserPresenter::class);
```

---

## Result

The `Result` class contains resolved data and metrics.

### Accessing Data

```php
// Magic getter
$result->user;

// Method access
$result->get('user');
$result->get('user', $default);

// Array access
$result['user'];

// Check existence
$result->has('user');
isset($result['user']);
```

### Getting Subsets

```php
// All data
$data = $result->all();

// Only specific keys
$data = $result->only(['user', 'posts']);

// Exclude keys
$data = $result->except(['metrics']);
```

### Transforming

```php
$transformed = $result->transform([
    'posts' => fn($posts) => $posts->take(5),
    'user' => fn($user) => $user->only(['id', 'name']),
]);
```

### Merging Results

```php
$combined = $result1->merge($result2);
```

### Mapping to DTOs

```php
// With constructor spreading
class DashboardDTO {
    public function __construct(
        public User $user,
        public DataSet $posts,
        public int $totalPosts,
    ) {}
}

$dto = $result->mapTo(DashboardDTO::class);

// With fromResult method
class DashboardDTO {
    public static function fromResult(Result $result): self {
        return new self(
            user: $result->user,
            posts: $result->posts->take(5),
        );
    }
}

$dto = $result->mapTo(DashboardDTO::class);
```

### Metrics

```php
$metrics = $result->metrics();
// [
//     'queries' => 5,
//     'cache_hits' => 2,
//     'batch_savings' => 3,
//     'time_ms' => 45.2,
//     'memory_mb' => 2.1,
//     'peak_memory_mb' => 4.5,
// ]
```

### API Response Format

```php
// Data only
$response = $result->toResponse();
// { "data": { "user": {...}, "posts": [...] } }

// With metrics
$response = $result->toResponse(includeMetrics: true);
// {
//     "data": { "user": {...}, "posts": [...] },
//     "meta": { "queries": 3, "time_ms": 12.5 }
// }
```

### Serialization

```php
$array = $result->toArray();
$json = $result->toJson();
json_encode($result); // Works directly
```

---

## DataSet

Query results are returned as lazy `DataSet` collections.

### Iteration

```php
foreach ($result->posts as $post) {
    echo $post->title;
}

$result->posts->each(fn($post) => process($post));
```

### Transformation (Lazy)

These operations don't execute until you iterate:

```php
$titles = $result->posts
    ->filter(fn($post) => $post->published)
    ->map(fn($post) => $post->title);

// Now iterate to execute
foreach ($titles as $title) {
    echo $title;
}
```

### Retrieval

```php
$first = $result->posts->first();
$first = $result->posts->first($default);

$last = $result->posts->last();

$found = $result->posts->find(fn($post) => $post->id === 5);
```

### Slicing

```php
$top5 = $result->posts->take(5);
$rest = $result->posts->skip(5);
```

### Field Extraction

```php
$titles = $result->posts->pluck('title');
$titleById = $result->posts->pluck('title', 'id');
```

### Grouping

```php
$byId = $result->posts->keyBy('id');
$byCategory = $result->posts->groupBy('category_id');
```

### Reduction

```php
$totalViews = $result->posts->reduce(
    fn($sum, $post) => $sum + $post->view_count,
    0
);
```

### Checking

```php
$hasPublished = $result->posts->contains(fn($post) => $post->published);
$allPublished = $result->posts->every(fn($post) => $post->published);

$isEmpty = $result->posts->isEmpty();
$isNotEmpty = $result->posts->isNotEmpty();
$count = $result->posts->count();
```

### Chunking

Process large datasets in memory-efficient chunks:

```php
$result->posts->chunk(100, function ($chunk, $index) {
    foreach ($chunk as $post) {
        // Process post
    }
});
```

### Conversion

```php
$array = $result->posts->all();
$array = $result->posts->toArray();  // Deep conversion
$collection = $result->posts->collect(); // Laravel Collection
```

---

## PaginatedResult

Paginated queries return `PaginatedResult` objects.

### Pagination Info

```php
$result->posts->total();        // Total records
$result->posts->perPage();      // Items per page
$result->posts->currentPage();  // Current page number
$result->posts->lastPage();     // Last page number
$result->posts->hasMorePages(); // Has more pages?
$result->posts->onFirstPage();  // On first page?
$result->posts->onLastPage();   // On last page?
```

### Iteration

```php
foreach ($result->posts as $post) {
    echo $post->title;
}

// Get as DataSet
$items = $result->posts->items();
```

### Access Underlying Paginator

```php
$paginator = $result->posts->getPaginator();
```

### Serialization

```php
$array = $result->posts->toArray();
// {
//     "data": [...],
//     "pagination": {
//         "total": 100,
//         "per_page": 15,
//         "current_page": 1,
//         "last_page": 7,
//         "has_more": true
//     }
// }
```

---

## Using the Facade

```php
use AdroSoftware\DataProxy\Laravel\DataProxyFacade as Data;

// All DataProxy methods available
$result = Data::fetch($requirements);
$result = Data::query(fn($r) => $r->one('user', User::class, 1));
$result = Data::forApi()->fetch($requirements);
$result = Data::configure(['cache.ttl' => 3600])->fetch($requirements);
```

---

## Next Steps

- [Use Cases](use-cases.md) - Real-world examples
- [Configuration](configuration.md) - All configuration options
- [API Reference](api-reference.md) - Complete method signatures
