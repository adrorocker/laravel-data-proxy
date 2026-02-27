# Getting Started

This guide will help you install and configure Laravel Data Proxy, and write your first data query.

## Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x

## Installation

Install the package via Composer:

```bash
composer require adrosoftware/laravel-data-proxy
```

### Auto-Discovery

Laravel Data Proxy uses Laravel's package auto-discovery. The service provider and facade are registered automatically.

### Manual Registration (Optional)

If auto-discovery is disabled, add the provider and alias to `config/app.php`:

```php
'providers' => [
    // ...
    AdroSoftware\DataProxy\Laravel\DataProxyServiceProvider::class,
],

'aliases' => [
    // ...
    'DataProxy' => AdroSoftware\DataProxy\Laravel\DataProxyFacade::class,
],
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=dataproxy-config
```

This creates `config/dataproxy.php` where you can customize caching, query limits, memory settings, and more. See [Configuration](configuration.md) for all options.

## Your First Query

Let's fetch a user with their profile and recent posts:

```php
use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use App\Models\User;
use App\Models\Post;

// Define what data you need
$result = DataProxy::make()->fetch(
    Requirements::make()
        ->one('user', User::class, 1,
            Shape::make()
                ->select('id', 'name', 'email')
                ->with('profile')
        )
        ->query('posts', Post::class,
            Shape::make()
                ->where('user_id', 1)
                ->latest()
                ->limit(5)
        )
);

// Use the data
echo $result->user->name;        // John Doe
echo $result->user->profile->bio; // Developer at...

foreach ($result->posts as $post) {
    echo $post->title;
}
```

## Understanding the Flow

### 1. Create a DataProxy Instance

```php
$proxy = DataProxy::make();
```

Or use configuration presets:

```php
$proxy = DataProxy::forApi();        // Optimized for API responses
$proxy = DataProxy::forExport();     // Optimized for large exports
$proxy = DataProxy::forPerformance(); // Maximum caching
```

### 2. Define Requirements

Requirements describe what data you need:

```php
$requirements = Requirements::make()
    ->one('user', User::class, $userId)           // Single entity
    ->many('authors', User::class, [1, 2, 3])     // Multiple entities
    ->query('posts', Post::class, $shape)         // Query with constraints
    ->count('total', Post::class);                // Aggregate
```

### 3. Define Shapes

Shapes describe the structure of data:

```php
$shape = Shape::make()
    ->select('id', 'title', 'body')      // Fields to select
    ->with('author')                      // Relations to load
    ->where('published', true)            // Constraints
    ->latest()                            // Ordering
    ->limit(10);                          // Limit
```

> **Note:** When using `select()` on relations, include the foreign key column to ensure the relationship works correctly. For example: `->with('posts', Shape::make()->select('id', 'user_id', 'title'))`

### 4. Fetch and Use

```php
$result = $proxy->fetch($requirements);

// Access data multiple ways
$result->user;           // Magic getter
$result->get('user');    // Method
$result['user'];         // Array access
```

## Query Batching

DataProxy automatically batches queries for the same model:

```php
$result = DataProxy::make()->fetch(
    Requirements::make()
        ->one('user1', User::class, 1)
        ->one('user2', User::class, 2)
        ->one('user3', User::class, 3)
);

// Only 1 query executed: SELECT * FROM users WHERE id IN (1, 2, 3)
```

Check the batch savings in metrics:

```php
$metrics = $result->metrics();
// ['queries' => 1, 'batch_savings' => 2, ...]
```

## Using with Controllers

```php
namespace App\Http\Controllers;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use App\Models\User;
use App\Models\Post;

class DashboardController extends Controller
{
    public function index()
    {
        $result = DataProxy::make()->fetch(
            Requirements::make()
                ->one('user', User::class, auth()->id(),
                    Shape::make()->with('profile'))
                ->query('recentPosts', Post::class,
                    Shape::make()
                        ->where('user_id', auth()->id())
                        ->latest()
                        ->limit(5)
                )
                ->count('totalPosts', Post::class,
                    Shape::make()->where('user_id', auth()->id())
                )
        );

        return view('dashboard', $result->all());
    }
}
```

## Using with API Controllers

```php
namespace App\Http\Controllers\Api;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use App\Models\User;

class UserController extends Controller
{
    public function show(int $id)
    {
        $result = DataProxy::forApi()->fetch(
            Requirements::make()
                ->one('user', User::class, $id,
                    Shape::make()
                        ->select('id', 'name', 'email', 'created_at')
                        ->with('profile', Shape::make()->select('bio', 'avatar'))
                )
        );

        if (!$result->user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json($result->toResponse());
    }
}
```

Response:

```json
{
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "created_at": "2024-01-15T10:30:00.000000Z",
            "profile": {
                "bio": "Developer",
                "avatar": "https://..."
            }
        }
    }
}
```

## Next Steps

- [Usage Guide](usage.md) - Complete API reference
- [Use Cases](use-cases.md) - Real-world examples
- [Configuration](configuration.md) - All configuration options
- [API Reference](api-reference.md) - Method signatures
