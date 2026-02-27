# API Reference

Complete method signatures for all Laravel Data Proxy classes.

## Schema Agnostic

DataProxy does not enforce any database schema. It reads configurations from your Eloquent models:

- **Primary Keys**: Reads `$primaryKey` from your model
- **Key Types**: Reads `$keyType` from your model
- **Foreign Keys**: Reads from your relationship definitions
- **Table Names**: Reads `$table` from your model

> **Note:** When using `select()` on relations, include the foreign key column to ensure the relationship hydrates correctly.

## DataProxy

The main entry point for data retrieval.

```php
namespace AdroSoftware\DataProxy;
```

### Static Methods

```php
/**
 * Create a new DataProxy instance
 */
public static function make(?Config $config = null): static

/**
 * Create instance optimized for API responses
 * - cache.enabled = true, cache.ttl = 300
 * - metrics.enabled = true
 */
public static function forApi(): static

/**
 * Create instance optimized for large exports
 * - cache.enabled = false
 * - query.chunk_size = 2000
 * - memory.max_mb = 512
 * - metrics.enabled = false
 */
public static function forExport(): static

/**
 * Create instance optimized for performance
 * - cache.enabled = true, cache.ttl = 3600
 * - metrics.enabled = false
 */
public static function forPerformance(): static
```

### Instance Methods

```php
/**
 * Use a custom cache adapter
 */
public function withCache(CacheAdapterInterface $cache): static

/**
 * Disable caching
 */
public function withoutCache(): static

/**
 * Use a presenter adapter
 */
public function withPresenter(PresenterAdapterInterface $presenter): static

/**
 * Update configuration
 * @param array|callable $config Configuration array or callback
 */
public function configure(array|callable $config): static

/**
 * Get the current configuration
 */
public function getConfig(): Config

/**
 * Fetch data based on requirements
 */
public function fetch(Requirements $requirements): Result

/**
 * Fetch with inline requirements builder
 */
public function query(callable $builder): Result
```

---

## Requirements

Defines what data to fetch.

```php
namespace AdroSoftware\DataProxy;
```

### Static Methods

```php
/**
 * Create a new Requirements instance
 */
public static function make(): static
```

### Entity Methods

```php
/**
 * Fetch a single entity by ID
 * @param string $alias Result key name
 * @param string $model Model class name
 * @param int|string|callable $id Primary key value or callable
 * @param Shape|null $shape Optional shape definition
 */
public function one(
    string $alias,
    string $model,
    int|string|callable $id,
    ?Shape $shape = null
): static

/**
 * Fetch multiple entities by IDs
 * @param string $alias Result key name
 * @param string $model Model class name
 * @param array|callable $ids Primary key values or callable
 * @param Shape|null $shape Optional shape definition
 */
public function many(
    string $alias,
    string $model,
    array|callable $ids,
    ?Shape $shape = null
): static
```

### Query Methods

```php
/**
 * Fetch entities matching constraints
 * @param string $alias Result key name
 * @param string $model Model class name
 * @param Shape|null $shape Shape with constraints
 */
public function query(
    string $alias,
    string $model,
    ?Shape $shape = null
): static

/**
 * Fetch paginated results
 * @param string $alias Result key name
 * @param string $model Model class name
 * @param int $perPage Items per page
 * @param int|null $page Page number (null = from request)
 * @param Shape|null $shape Shape with constraints
 */
public function paginate(
    string $alias,
    string $model,
    int $perPage = 15,
    ?int $page = null,
    ?Shape $shape = null
): static
```

### Aggregate Methods

```php
/**
 * Count matching records
 */
public function count(string $alias, string $model, ?Shape $shape = null): static

/**
 * Sum a column
 */
public function sum(string $alias, string $model, string $column, ?Shape $shape = null): static

/**
 * Average a column
 */
public function avg(string $alias, string $model, string $column, ?Shape $shape = null): static

/**
 * Minimum value of a column
 */
public function min(string $alias, string $model, string $column, ?Shape $shape = null): static

/**
 * Maximum value of a column
 */
public function max(string $alias, string $model, string $column, ?Shape $shape = null): static
```

### Raw SQL

```php
/**
 * Execute raw SQL query
 * @param string $alias Result key name
 * @param string $sql SQL query
 * @param array $bindings Query bindings
 */
public function raw(string $alias, string $sql, array $bindings = []): static
```

### Computed Values

```php
/**
 * Compute a derived value
 * @param string $alias Result key name
 * @param callable $computer Function receiving resolved data
 * @param array $dependsOn Aliases this depends on
 */
public function compute(
    string $alias,
    callable $computer,
    array $dependsOn = []
): static
```

### Caching

```php
/**
 * Configure caching for a requirement
 * @param string $alias Requirement alias to cache
 * @param string $key Cache key
 * @param int|null $ttl Time to live in seconds
 * @param array $tags Cache tags
 */
public function cache(
    string $alias,
    string $key,
    ?int $ttl = null,
    array $tags = []
): static
```

### Getters

```php
public function getEntities(): array
public function getQueries(): array
public function getAggregates(): array
public function getComputed(): array
public function getRaw(): array
public function getCache(): array
public function all(): array
```

---

## Shape

Defines the structure of data to retrieve.

```php
namespace AdroSoftware\DataProxy;
```

### Static Methods

```php
/**
 * Create a new Shape instance
 */
public static function make(): static
```

### Field Selection

```php
/**
 * Select specific fields
 * @param string|array ...$fields Field names
 */
public function select(string|array ...$fields): static
```

### Relations

```php
/**
 * Include a relation
 * @param string|array $relation Relation name or array of relations
 * @param Shape|callable|null $shape Nested shape or query callback
 */
public function with(string|array $relation, Shape|callable|null $shape = null): static
```

### Constraints

```php
/**
 * Add a where clause
 * @param string $column Column name
 * @param mixed $operator Operator or value (if 2 args)
 * @param mixed $value Value (if 3 args)
 */
public function where(string $column, mixed $operator = null, mixed $value = null): static

/**
 * Add a whereIn clause
 * @param string $column Column name
 * @param array|callable $values Array of values or callable
 */
public function whereIn(string $column, array|callable $values): static

/**
 * Add a whereNotIn clause
 */
public function whereNotIn(string $column, array $values): static

/**
 * Add a whereBetween clause
 * @param string $column Column name
 * @param array $range [min, max] values
 */
public function whereBetween(string $column, array $range): static

/**
 * Add a whereNull clause
 */
public function whereNull(string $column): static

/**
 * Add a whereNotNull clause
 */
public function whereNotNull(string $column): static

/**
 * Add a whereHas clause
 * @param string $relation Relation name
 * @param callable|null $callback Query callback
 */
public function whereHas(string $relation, ?callable $callback = null): static

/**
 * Add a whereDoesntHave clause
 */
public function whereDoesntHave(string $relation, ?callable $callback = null): static

/**
 * Add a raw where clause
 * @param string $sql Raw SQL
 * @param array $bindings Query bindings
 */
public function whereRaw(string $sql, array $bindings = []): static

/**
 * Conditional constraint
 * @param bool $condition Apply callback if true
 * @param callable $callback Callback receiving Shape
 */
public function when(bool $condition, callable $callback): static

/**
 * Apply a custom scope
 * @param callable $scope Function receiving (Builder, array $resolved)
 */
public function scope(callable $scope): static
```

### Ordering

```php
/**
 * Add ordering
 * @param string $column Column name
 * @param string $direction 'asc' or 'desc'
 */
public function orderBy(string $column, string $direction = 'asc'): static

/**
 * Order by column descending (default: created_at)
 */
public function latest(string $column = 'created_at'): static

/**
 * Order by column ascending (default: created_at)
 */
public function oldest(string $column = 'created_at'): static
```

### Limiting

```php
/**
 * Limit results
 */
public function limit(int $limit): static

/**
 * Offset results
 */
public function offset(int $offset): static

/**
 * Alias for limit
 */
public function take(int $count): static

/**
 * Alias for offset
 */
public function skip(int $count): static
```

### Output Format

```php
/**
 * Apply a presenter class
 */
public function present(string $presenterClass): static

/**
 * Return as arrays instead of models
 */
public function asArray(): static
```

### Getters

```php
public function getFields(): array
public function getRelations(): array
public function getConstraints(): array
public function getLimit(): ?int
public function getOffset(): ?int
public function getOrderBy(): array
public function getScope(): ?callable
public function getPresenter(): ?string
public function shouldReturnArray(): bool
```

---

## Result

Contains resolved data and metrics.

```php
namespace AdroSoftware\DataProxy;

class Result implements Arrayable, Jsonable, JsonSerializable, ArrayAccess
```

### Constructor

```php
public function __construct(array $data, array $metrics = [])
```

### Accessing Data

```php
/**
 * Get a value by key
 */
public function get(string $key, mixed $default = null): mixed

/**
 * Magic getter
 */
public function __get(string $name): mixed

/**
 * Check if key exists
 */
public function has(string $key): bool

/**
 * Magic isset
 */
public function __isset(string $name): bool
```

### Data Retrieval

```php
/**
 * Get all data
 */
public function all(): array

/**
 * Get only specified keys
 */
public function only(array $keys): array

/**
 * Get all except specified keys
 */
public function except(array $keys): array

/**
 * Get count of data items
 */
public function count(): int

/**
 * Check if empty
 */
public function isEmpty(): bool

/**
 * Check if not empty
 */
public function isNotEmpty(): bool
```

### Metrics

```php
/**
 * Get execution metrics
 * @return array{queries: int, cache_hits: int, batch_savings: int, time_ms: float, memory_mb: float, peak_memory_mb: float}
 */
public function metrics(): array
```

### Transformation

```php
/**
 * Merge with another result
 */
public function merge(Result $other): static

/**
 * Transform specific values
 * @param array $transformers Key => transformer callable
 */
public function transform(array $transformers): static

/**
 * Map to a DTO class
 * @throws \InvalidArgumentException if class doesn't exist
 */
public function mapTo(string $class): object
```

### Serialization

```php
public function toArray(): array
public function toJson($options = 0): string
public function jsonSerialize(): array

/**
 * Format for API responses
 * @param bool $includeMetrics Include metrics in meta key
 */
public function toResponse(bool $includeMetrics = false): array
```

### ArrayAccess

```php
public function offsetExists(mixed $offset): bool
public function offsetGet(mixed $offset): mixed
public function offsetSet(mixed $offset, mixed $value): void  // Throws LogicException
public function offsetUnset(mixed $offset): void  // Throws LogicException
```

---

## DataSet

Lazy, memory-efficient data collection.

```php
namespace AdroSoftware\DataProxy;

class DataSet implements IteratorAggregate, Countable, Arrayable, JsonSerializable
```

### Static Methods

```php
/**
 * Create empty dataset
 */
public static function empty(): static

/**
 * Create from iterable
 */
public static function from(iterable $source, ?int $count = null): static
```

### Constructor

```php
public function __construct(iterable $source, ?int $count = null)
```

### Transformation (Lazy)

```php
/**
 * Map items (lazy evaluation)
 */
public function map(callable $callback): static

/**
 * Filter items (lazy evaluation)
 */
public function filter(?callable $callback = null): static
```

### Retrieval

```php
/**
 * Get first item
 */
public function first(mixed $default = null): mixed

/**
 * Get last item (requires iteration)
 */
public function last(mixed $default = null): mixed

/**
 * Find item by callback
 */
public function find(callable $callback, mixed $default = null): mixed
```

### Slicing

```php
/**
 * Take first N items
 */
public function take(int $limit): static

/**
 * Skip first N items
 */
public function skip(int $offset): static
```

### Field Extraction

```php
/**
 * Pluck field values
 * @param string $field Field to pluck
 * @param string|null $keyBy Optional key field
 */
public function pluck(string $field, ?string $keyBy = null): static

/**
 * Key items by field
 * @param string|callable $key Field name or callback
 */
public function keyBy(string|callable $key): array

/**
 * Group items by field
 * @param string|callable $key Field name or callback
 */
public function groupBy(string|callable $key): array
```

### Reduction

```php
/**
 * Reduce to single value
 */
public function reduce(callable $callback, mixed $initial = null): mixed
```

### Iteration

```php
/**
 * Iterate with callback
 * @return static Returns false from callback to break
 */
public function each(callable $callback): static

/**
 * Process in chunks
 * @param int $size Chunk size
 * @param callable $callback Receives (array $chunk, int $index)
 */
public function chunk(int $size, callable $callback): void
```

### Checking

```php
/**
 * Check if any item matches
 */
public function contains(callable $callback): bool

/**
 * Check if all items match
 */
public function every(callable $callback): bool

/**
 * Check if empty
 */
public function isEmpty(): bool

/**
 * Check if not empty
 */
public function isNotEmpty(): bool

/**
 * Get count (may require iteration)
 */
public function count(): int
```

### Conversion

```php
/**
 * Materialize to array
 */
public function all(): array

/**
 * Get as Laravel Collection
 */
public function collect(): Collection

public function toArray(): array
public function jsonSerialize(): array
public function getIterator(): Traversable
```

---

## PaginatedResult

Wrapper for paginated data.

```php
namespace AdroSoftware\DataProxy;

class PaginatedResult implements Arrayable, JsonSerializable, IteratorAggregate, Countable
```

### Constructor

```php
public function __construct(LengthAwarePaginator $paginator)
```

### Pagination Info

```php
/**
 * Get items as DataSet
 */
public function items(): DataSet

/**
 * Get total count across all pages
 */
public function total(): int

/**
 * Get items per page
 */
public function perPage(): int

/**
 * Get current page number
 */
public function currentPage(): int

/**
 * Get last page number
 */
public function lastPage(): int

/**
 * Check if there are more pages
 */
public function hasMorePages(): bool

/**
 * Check if on first page
 */
public function onFirstPage(): bool

/**
 * Check if on last page
 */
public function onLastPage(): bool

/**
 * Get underlying paginator
 */
public function getPaginator(): LengthAwarePaginator
```

### Interfaces

```php
public function getIterator(): Traversable
public function count(): int
public function toArray(): array
public function jsonSerialize(): array
```

---

## Config

Configuration management.

```php
namespace AdroSoftware\DataProxy;
```

### Constructor

```php
public function __construct(array $config = [])
```

### Methods

```php
/**
 * Get config value by dot notation key
 */
public function get(string $key, mixed $default = null): mixed

/**
 * Set config value by dot notation key
 */
public function set(string $key, mixed $value): static

/**
 * Get all config
 */
public function all(): array

/**
 * Merge config
 */
public function merge(array $config): static
```

---

## Contracts

### CacheAdapterInterface

```php
namespace AdroSoftware\DataProxy\Contracts;

interface CacheAdapterInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): void;
    public function has(string $key): bool;
    public function forget(string $key): void;
    public function tags(array $tags): static;
}
```

### PresenterAdapterInterface

```php
namespace AdroSoftware\DataProxy\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PresenterAdapterInterface
{
    public function present(Model $model, ?string $presenterClass = null): mixed;
    public function hasPresenter(Model $model): bool;
    public function resolvePresenter(Model $model): ?string;
}
```

---

## Adapters

### LaravelCacheAdapter

```php
namespace AdroSoftware\DataProxy\Adapters;

class LaravelCacheAdapter implements CacheAdapterInterface
{
    public function __construct(?string $store = null);
}
```

### LaravelModelPresenterAdapter

```php
namespace AdroSoftware\DataProxy\Adapters;

class LaravelModelPresenterAdapter implements PresenterAdapterInterface
{
    public function __construct(
        string $namespace = 'App\\Presenters\\',
        string $suffix = 'Presenter'
    );

    public function register(string $modelClass, string $presenterClass): static;
    public function registerMany(array $mappings): static;
    public function setNamespace(string $namespace): static;
    public function setSuffix(string $suffix): static;
}
```

### ClosurePresenterAdapter

```php
namespace AdroSoftware\DataProxy\Adapters;

class ClosurePresenterAdapter implements PresenterAdapterInterface
{
    public function register(string $modelClass, callable $presenter): static;
    public function registerMany(array $presenters): static;
}
```

---

## Facade

```php
namespace AdroSoftware\DataProxy\Laravel;

/**
 * @method static Result fetch(Requirements $requirements)
 * @method static Result query(callable $builder)
 * @method static DataProxy withCache(CacheAdapterInterface $cache)
 * @method static DataProxy withoutCache()
 * @method static DataProxy withPresenter(PresenterAdapterInterface $presenter)
 * @method static DataProxy configure(array|callable $config)
 * @method static DataProxy forApi()
 * @method static DataProxy forExport()
 * @method static DataProxy forPerformance()
 *
 * @see DataProxy
 */
class DataProxyFacade extends Facade
{
    protected static function getFacadeAccessor(): string;
}
```
