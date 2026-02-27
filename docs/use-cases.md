# Use Cases

Real-world examples and patterns for using Laravel Data Proxy effectively.

## Dashboard Data Fetching

Load all data needed for a dashboard in a single, optimized call:

```php
namespace App\Data;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use AdroSoftware\DataProxy\Result;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Notification;

class DashboardData
{
    public static function fetch(int $userId): Result
    {
        return DataProxy::make()->fetch(
            Requirements::make()
                // Current user with profile
                ->one('user', User::class, $userId,
                    Shape::make()
                        ->select('id', 'name', 'email', 'avatar')
                        ->with('profile')
                        ->with('roles', Shape::make()->select('id', 'name'))
                )

                // Recent posts
                ->query('recentPosts', Post::class,
                    Shape::make()
                        ->where('user_id', $userId)
                        ->latest()
                        ->limit(5)
                        ->with('category')
                )

                // Recent comments on user's posts
                ->query('recentComments', Comment::class,
                    Shape::make()
                        ->scope(fn($q, $r) =>
                            $q->whereIn('post_id', $r['recentPosts']->pluck('id')->all())
                        )
                        ->with('author', Shape::make()->select('id', 'name', 'avatar'))
                        ->latest()
                        ->limit(10)
                )

                // Unread notifications
                ->query('notifications', Notification::class,
                    Shape::make()
                        ->where('user_id', $userId)
                        ->whereNull('read_at')
                        ->latest()
                        ->limit(5)
                )

                // Statistics
                ->count('totalPosts', Post::class,
                    Shape::make()->where('user_id', $userId)
                )
                ->count('totalPublished', Post::class,
                    Shape::make()
                        ->where('user_id', $userId)
                        ->where('published', true)
                )
                ->sum('totalViews', Post::class, 'view_count',
                    Shape::make()->where('user_id', $userId)
                )

                // Computed stats
                ->compute('stats', fn($d) => [
                    'posts' => $d['totalPosts'],
                    'published' => $d['totalPublished'],
                    'drafts' => $d['totalPosts'] - $d['totalPublished'],
                    'views' => $d['totalViews'],
                    'avgViews' => $d['totalPosts'] > 0
                        ? round($d['totalViews'] / $d['totalPosts'])
                        : 0,
                ], ['totalPosts', 'totalPublished', 'totalViews'])
        );
    }
}

// Controller
class DashboardController extends Controller
{
    public function index()
    {
        $data = DashboardData::fetch(auth()->id());

        return view('dashboard', [
            'user' => $data->user,
            'recentPosts' => $data->recentPosts,
            'recentComments' => $data->recentComments,
            'notifications' => $data->notifications,
            'stats' => $data->stats,
        ]);
    }
}
```

---

## API Endpoints

Build efficient API responses with metrics:

```php
namespace App\Http\Controllers\Api;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use App\Models\Post;
use App\Models\User;

class PostController extends Controller
{
    public function index()
    {
        $result = DataProxy::forApi()->fetch(
            Requirements::make()
                ->paginate('posts', Post::class,
                    perPage: 15,
                    page: request('page', 1),
                    shape: Shape::make()
                        ->select('id', 'title', 'excerpt', 'published_at', 'view_count')
                        ->where('published', true)
                        ->when(request('category'), fn($s) =>
                            $s->where('category_id', request('category'))
                        )
                        ->when(request('search'), fn($s) =>
                            $s->whereRaw('title LIKE ?', ['%' . request('search') . '%'])
                        )
                        ->with('author', Shape::make()->select('id', 'name', 'avatar'))
                        ->with('category', Shape::make()->select('id', 'name', 'slug'))
                        ->latest('published_at')
                )
        );

        return response()->json([
            'data' => $result->posts->toArray()['data'],
            'pagination' => $result->posts->toArray()['pagination'],
            'meta' => $result->metrics(),
        ]);
    }

    public function show(int $id)
    {
        $result = DataProxy::forApi()->fetch(
            Requirements::make()
                ->one('post', Post::class, $id,
                    Shape::make()
                        ->select('id', 'title', 'content', 'published_at', 'view_count')
                        ->where('published', true)
                        ->with('author', Shape::make()
                            ->select('id', 'name', 'bio', 'avatar')
                            ->with('profile')
                        )
                        ->with('category')
                        ->with('tags', Shape::make()->select('id', 'name', 'slug'))
                        ->with('comments', Shape::make()
                            ->where('approved', true)
                            ->with('author', Shape::make()->select('id', 'name', 'avatar'))
                            ->latest()
                            ->limit(20)
                        )
                )
                ->query('relatedPosts', Post::class,
                    Shape::make()
                        ->scope(fn($q, $r) =>
                            $q->where('category_id', $r['post']?->category_id)
                              ->where('id', '!=', $id)
                        )
                        ->where('published', true)
                        ->select('id', 'title', 'excerpt', 'published_at')
                        ->with('author', Shape::make()->select('id', 'name'))
                        ->latest()
                        ->limit(5)
                )
        );

        if (!$result->post) {
            return response()->json(['error' => 'Post not found'], 404);
        }

        return response()->json($result->toResponse(includeMetrics: true));
    }
}
```

---

## Data Exports

Handle large datasets efficiently with chunking:

```php
namespace App\Exports;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use App\Models\Order;
use League\Csv\Writer;

class OrderExport
{
    public function export(array $filters = []): string
    {
        $result = DataProxy::forExport()->fetch(
            Requirements::make()
                ->query('orders', Order::class,
                    Shape::make()
                        ->select('id', 'order_number', 'total', 'status', 'created_at')
                        ->when($filters['status'] ?? null, fn($s) =>
                            $s->where('status', $filters['status'])
                        )
                        ->when($filters['date_from'] ?? null, fn($s) =>
                            $s->where('created_at', '>=', $filters['date_from'])
                        )
                        ->when($filters['date_to'] ?? null, fn($s) =>
                            $s->where('created_at', '<=', $filters['date_to'])
                        )
                        ->with('customer', Shape::make()->select('id', 'name', 'email'))
                        ->with('items', Shape::make()
                            ->select('id', 'product_name', 'quantity', 'price')
                        )
                        ->orderBy('created_at', 'desc')
                        ->asArray()
                )
        );

        $csv = Writer::createFromString();
        $csv->insertOne(['Order #', 'Customer', 'Email', 'Total', 'Status', 'Items', 'Date']);

        // Process in chunks to manage memory
        $result->orders->chunk(500, function ($chunk) use ($csv) {
            foreach ($chunk as $order) {
                $csv->insertOne([
                    $order['order_number'],
                    $order['customer']['name'],
                    $order['customer']['email'],
                    number_format($order['total'], 2),
                    $order['status'],
                    count($order['items']),
                    $order['created_at'],
                ]);
            }
        });

        return $csv->toString();
    }
}
```

---

## Caching Strategies

### Per-Requirement Caching

```php
$result = DataProxy::make()->fetch(
    Requirements::make()
        // Categories rarely change - cache for 1 hour
        ->query('categories', Category::class,
            Shape::make()->where('active', true)->orderBy('name')
        )
        ->cache('categories', 'categories:active', ttl: 3600, tags: ['categories'])

        // User-specific data - cache for 5 minutes
        ->query('userPosts', Post::class,
            Shape::make()->where('user_id', $userId)->latest()->limit(10)
        )
        ->cache('userPosts', "user:{$userId}:posts", ttl: 300, tags: ['user', "user:{$userId}"])

        // Real-time data - no caching
        ->count('onlineUsers', User::class,
            Shape::make()->where('last_seen_at', '>', now()->subMinutes(5))
        )
);

// Invalidate caches
Cache::tags(['categories'])->flush();
Cache::tags(["user:{$userId}"])->flush();
```

### Global vs Per-User Caching

```php
namespace App\Data;

class NavigationData
{
    public static function fetch(): Result
    {
        return DataProxy::make()->fetch(
            Requirements::make()
                // Global navigation - same for all users
                ->query('mainMenu', MenuItem::class,
                    Shape::make()
                        ->where('location', 'main')
                        ->where('active', true)
                        ->orderBy('order')
                        ->with('children')
                )
                ->cache('mainMenu', 'nav:main', ttl: 3600, tags: ['navigation'])

                // User-specific quick links
                ->query('quickLinks', QuickLink::class,
                    Shape::make()
                        ->where('user_id', auth()->id())
                        ->orderBy('order')
                        ->limit(5)
                )
                ->cache('quickLinks', 'nav:quick:' . auth()->id(), ttl: 300, tags: ['navigation', 'user:' . auth()->id()])
        );
    }
}
```

---

## Query Batching (N+1 Prevention)

DataProxy automatically batches queries:

```php
// Without DataProxy - potential N+1
$author1 = User::find(1);  // Query 1
$author2 = User::find(2);  // Query 2
$author3 = User::find(3);  // Query 3

// With DataProxy - single query
$result = DataProxy::make()->fetch(
    Requirements::make()
        ->one('author1', User::class, 1)
        ->one('author2', User::class, 2)
        ->one('author3', User::class, 3)
);
// Result: 1 query with WHERE id IN (1, 2, 3)

echo $result->metrics()['queries'];       // 1
echo $result->metrics()['batch_savings']; // 2
```

### Batching Aggregates

```php
// Without DataProxy - 3 queries
$totalPosts = Post::count();
$totalViews = Post::sum('views');
$avgRating = Post::avg('rating');

// With DataProxy - 1 query
$result = DataProxy::make()->fetch(
    Requirements::make()
        ->count('totalPosts', Post::class)
        ->sum('totalViews', Post::class, 'views')
        ->avg('avgRating', Post::class, 'rating')
);
// Result: SELECT COUNT(*) as totalPosts, SUM(views) as totalViews, AVG(rating) as avgRating FROM posts
```

---

## View Model Pattern

Create dedicated view models for different contexts:

```php
namespace App\ViewModels;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use AdroSoftware\DataProxy\Result;
use App\Models\Post;

class PostListViewModel
{
    private Result $result;

    public function __construct(int $page = 1, ?int $categoryId = null)
    {
        $this->result = DataProxy::make()->fetch(
            Requirements::make()
                ->paginate('posts', Post::class,
                    perPage: 12,
                    page: $page,
                    shape: Shape::make()
                        ->select('id', 'title', 'excerpt', 'thumbnail', 'published_at')
                        ->where('published', true)
                        ->when($categoryId, fn($s) => $s->where('category_id', $categoryId))
                        ->with('author', Shape::make()->select('id', 'name', 'avatar'))
                        ->with('category', Shape::make()->select('id', 'name', 'slug'))
                        ->latest('published_at')
                )
                ->query('categories', Category::class,
                    Shape::make()->where('active', true)->orderBy('name')
                )
                ->count('totalPublished', Post::class,
                    Shape::make()->where('published', true)
                )
        );
    }

    public function posts(): PaginatedResult
    {
        return $this->result->posts;
    }

    public function categories(): DataSet
    {
        return $this->result->categories;
    }

    public function totalPublished(): int
    {
        return $this->result->totalPublished;
    }

    public function currentCategory(): ?Category
    {
        return $this->categories()->find(fn($c) => $c->id === request('category'));
    }

    public function isEmpty(): bool
    {
        return $this->result->posts->total() === 0;
    }
}

// Controller
class PostController extends Controller
{
    public function index()
    {
        $viewModel = new PostListViewModel(
            page: request('page', 1),
            categoryId: request('category')
        );

        return view('posts.index', compact('viewModel'));
    }
}
```

---

## Presenter Integration

### With Laravel Model Presenter

```php
use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Adapters\LaravelModelPresenterAdapter;

// Configure once in a service provider
$presenter = new LaravelModelPresenterAdapter(
    namespace: 'App\\Presenters\\',
    suffix: 'Presenter'
);

// Or register explicit mappings
$presenter->register(User::class, UserPresenter::class);
$presenter->register(Post::class, PostPresenter::class);

// Use in queries
$result = DataProxy::make()
    ->withPresenter($presenter)
    ->fetch(
        Requirements::make()
            ->one('user', User::class, $id,
                Shape::make()->present(UserPresenter::class)
            )
            ->query('posts', Post::class,
                Shape::make()
                    ->where('user_id', $id)
                    ->present(PostPresenter::class)
            )
    );

// Presenter methods available
echo $result->user->fullName();        // From UserPresenter
echo $result->user->formattedJoinDate(); // From UserPresenter

foreach ($result->posts as $post) {
    echo $post->readingTime();         // From PostPresenter
    echo $post->formattedPublishDate(); // From PostPresenter
}
```

### With Closures (No Package Required)

```php
use AdroSoftware\DataProxy\Adapters\ClosurePresenterAdapter;

$presenter = new ClosurePresenterAdapter();

$presenter->register(User::class, function ($user) {
    return new class($user) {
        public function __construct(private $user) {}

        public function __get($name)
        {
            return $this->user->{$name};
        }

        public function fullName(): string
        {
            return trim($this->user->first_name . ' ' . $this->user->last_name);
        }

        public function avatarUrl(): string
        {
            return $this->user->avatar
                ? asset('storage/' . $this->user->avatar)
                : asset('images/default-avatar.png');
        }

        public function memberSince(): string
        {
            return $this->user->created_at->diffForHumans();
        }
    };
});

$result = DataProxy::make()
    ->withPresenter($presenter)
    ->fetch($requirements);
```

---

## Multi-Tenant Data

Handle tenant isolation:

```php
namespace App\Data;

class TenantAwareData
{
    public static function fetch(string $tenantId): Result
    {
        return DataProxy::make()->fetch(
            Requirements::make()
                // All queries automatically scoped to tenant
                ->query('users', User::class,
                    Shape::make()
                        ->where('tenant_id', $tenantId)
                        ->with('roles')
                )
                ->query('projects', Project::class,
                    Shape::make()
                        ->where('tenant_id', $tenantId)
                        ->where('active', true)
                        ->with('members')
                )
                ->count('totalUsers', User::class,
                    Shape::make()->where('tenant_id', $tenantId)
                )

                // Cache per tenant
                ->cache('users', "tenant:{$tenantId}:users", ttl: 300)
                ->cache('projects', "tenant:{$tenantId}:projects", ttl: 300)
        );
    }
}
```

---

## Polymorphic Relations

Handle polymorphic data:

```php
$result = DataProxy::make()->fetch(
    Requirements::make()
        ->query('activities', Activity::class,
            Shape::make()
                ->where('user_id', $userId)
                ->latest()
                ->limit(20)
                ->with(['subject' => fn($q) => $q->morphWith([
                    Post::class => ['author', 'category'],
                    Comment::class => ['post', 'author'],
                    Like::class => ['likeable'],
                ])])
        )
);

foreach ($result->activities as $activity) {
    match ($activity->subject_type) {
        Post::class => "Posted: {$activity->subject->title}",
        Comment::class => "Commented on: {$activity->subject->post->title}",
        Like::class => "Liked: {$activity->subject->likeable->title}",
    };
}
```

---

## Testing

Mock DataProxy in tests:

```php
namespace Tests\Feature;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Result;

class DashboardTest extends TestCase
{
    public function test_dashboard_loads_user_data()
    {
        $user = User::factory()->create();
        $posts = Post::factory()->count(5)->for($user)->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('user')
            ->assertViewHas('recentPosts');
    }

    public function test_dashboard_with_mocked_data()
    {
        // Create a mock result
        $result = new Result([
            'user' => (object) ['id' => 1, 'name' => 'Test User'],
            'posts' => collect([]),
            'totalPosts' => 0,
        ]);

        // Mock the DataProxy
        $this->mock(DataProxy::class, function ($mock) use ($result) {
            $mock->shouldReceive('make->fetch')->andReturn($result);
        });

        $this->get('/dashboard')->assertOk();
    }
}
```

---

## Next Steps

- [Configuration](configuration.md) - All configuration options
- [API Reference](api-reference.md) - Complete method signatures
