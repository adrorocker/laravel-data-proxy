<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

use AdroSoftware\DataProxy\Contracts\CacheAdapterInterface;
use AdroSoftware\DataProxy\Contracts\PresenterAdapterInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Resolves all requirements with optimization and batching
 */
class Resolver
{
    protected Requirements $requirements;
    protected Config $config;
    protected ?CacheAdapterInterface $cache;
    protected ?PresenterAdapterInterface $presenter;

    /** @var array<string, mixed> */
    protected array $resolved = [];

    /** @var array<string, int|float> */
    protected array $metrics = [
        'queries' => 0,
        'cache_hits' => 0,
        'batch_savings' => 0,
    ];

    public function __construct(
        Requirements $requirements,
        Config $config,
        ?CacheAdapterInterface $cache = null,
        ?PresenterAdapterInterface $presenter = null,
    ) {
        $this->requirements = $requirements;
        $this->config = $config;
        $this->cache = $cache;
        $this->presenter = $presenter;
    }

    public function resolve(): Result
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Phase 1: Check cache for everything
        $this->resolveFromCache();

        // Phase 2: Batch entity lookups by model
        $this->resolveEntities();

        // Phase 3: Execute queries
        $this->resolveQueries();

        // Phase 4: Batch aggregates
        $this->resolveAggregates();

        // Phase 5: Raw SQL
        $this->resolveRaw();

        // Phase 6: Computed values (dependency order)
        $this->resolveComputed();

        // Phase 7: Apply presenters
        $this->applyPresenters();

        // Phase 8: Store results in cache
        $this->storeInCache();

        // Metrics
        if ($this->config->get('metrics.enabled')) {
            $this->metrics['time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            $this->metrics['memory_mb'] = round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2);
            $this->metrics['peak_memory_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        }

        return new Result($this->resolved, $this->metrics);
    }

    protected function resolveFromCache(): void
    {
        if (!$this->cache || !$this->config->get('cache.enabled')) {
            return;
        }

        $cacheConfig = $this->requirements->getCache();
        /** @var string $prefix */
        $prefix = $this->config->get('cache.prefix');

        foreach ($cacheConfig as $alias => $conf) {
            /** @var string $cacheKey */
            $cacheKey = $conf['key'];
            /** @var array<int, string> $tags */
            $tags = $conf['tags'] ?? [];

            $key = $this->hashCacheKey($prefix . $cacheKey);
            $cache = !empty($tags) ? $this->cache->tags($tags) : $this->cache;

            // Use atomic get() instead of has() + get() to prevent race condition
            $value = $cache->get($key);
            if ($value !== null) {
                $this->resolved[$alias] = $value;
                $this->metrics['cache_hits']++;
            }
        }
    }

    /**
     * Hash cache key to prevent cache key injection and ensure safe key format
     */
    protected function hashCacheKey(string $key): string
    {
        return 'dp_' . hash('sha256', $key);
    }

    protected function storeInCache(): void
    {
        if (!$this->cache || !$this->config->get('cache.enabled')) {
            return;
        }

        $cacheConfig = $this->requirements->getCache();
        /** @var string $prefix */
        $prefix = $this->config->get('cache.prefix');
        /** @var int|null $defaultTtl */
        $defaultTtl = $this->config->get('cache.ttl');

        foreach ($cacheConfig as $alias => $conf) {
            if (!isset($this->resolved[$alias])) {
                continue;
            }

            /** @var string $cacheKey */
            $cacheKey = $conf['key'];
            /** @var int|null $ttl */
            $ttl = $conf['ttl'] ?? $defaultTtl;
            /** @var array<int, string> $tags */
            $tags = $conf['tags'] ?? [];

            $key = $this->hashCacheKey($prefix . $cacheKey);
            $cache = !empty($tags) ? $this->cache->tags($tags) : $this->cache;

            $cache->set($key, $this->resolved[$alias], $ttl);
        }
    }

    protected function resolveEntities(): void
    {
        $entities = $this->requirements->getEntities();
        /** @var array<class-string<Model>, array<string, array<string, mixed>>> $byModel */
        $byModel = [];

        // Group by model for batching
        foreach ($entities as $alias => $entity) {
            if (isset($this->resolved[$alias])) {
                continue; // Already from cache
            }

            /** @var class-string<Model> $model */
            $model = $entity['model'];
            $byModel[$model] ??= [];
            $byModel[$model][$alias] = $entity;
        }

        // Execute one query per model
        foreach ($byModel as $modelClass => $aliasedEntities) {
            $this->batchResolveEntities($modelClass, $aliasedEntities);
        }
    }

    /**
     * @param class-string<Model> $modelClass
     * @param array<string, array<string, mixed>> $entities
     */
    protected function batchResolveEntities(string $modelClass, array $entities): void
    {
        // Validate model class
        $this->validateModelClass($modelClass);

        // Collect all IDs and relations
        $allIds = [];
        $allRelations = [];

        foreach ($entities as $alias => $entity) {
            if ($entity['type'] === 'one') {
                $id = $this->resolveValue($entity['id']);
                if ($id !== null) {
                    $allIds[] = $id;
                }
            } else {
                $ids = (array) $this->resolveValue($entity['ids']);
                $allIds = array_merge($allIds, $ids);
            }

            /** @var Shape $shape */
            $shape = $entity['shape'];
            $allRelations = array_merge($allRelations, $this->extractRelations($shape));
        }

        $allIds = array_values(array_unique(array_filter($allIds)));
        $allRelations = array_unique($allRelations);

        if (empty($allIds)) {
            foreach ($entities as $alias => $entity) {
                $this->resolved[$alias] = $entity['type'] === 'one' ? null : DataSet::empty();
            }
            return;
        }

        // Build single query
        $primaryKey = (new $modelClass())->getKeyName();
        $query = $modelClass::whereIn($primaryKey, $allIds);

        // Collect fields (union of all)
        $allFields = $this->collectFields($entities);
        if ($allFields !== ['*']) {
            // Always include primary key
            if (!in_array($primaryKey, $allFields)) {
                array_unshift($allFields, $primaryKey);
            }
            $query->select($allFields);
        }

        // Eager load relations with their shapes
        if (!empty($allRelations)) {
            $eagerLoads = $this->buildEagerLoads($entities);
            $query->with($eagerLoads);
        }

        $results = $query->get()->keyBy($primaryKey);
        $this->metrics['queries']++;
        $this->metrics['batch_savings'] += count($entities) - 1;

        // Distribute to aliases
        foreach ($entities as $alias => $entity) {
            /** @var Shape $shape */
            $shape = $entity['shape'];

            if ($entity['type'] === 'one') {
                $id = $this->resolveValue($entity['id']);
                /** @var int|string|null $resolvedId */
                $resolvedId = $id;
                $model = $results->get($resolvedId);
                $this->resolved[$alias] = $shape->shouldReturnArray() && $model
                    ? $model->toArray()
                    : $model;
            } else {
                $ids = (array) $this->resolveValue($entity['ids']);
                $items = collect($ids)
                    ->map(function (mixed $id) use ($results): ?Model {
                        if (!is_int($id) && !is_string($id)) {
                            return null;
                        }
                        return $results->get($id);
                    })
                    ->filter()
                    ->values();

                if ($shape->shouldReturnArray()) {
                    $items = $items->map->toArray();
                }

                $this->resolved[$alias] = new DataSet($items, $items->count());
            }
        }
    }

    protected function resolveQueries(): void
    {
        foreach ($this->requirements->getQueries() as $alias => $queryDef) {
            if (isset($this->resolved[$alias])) {
                continue;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $queryDef['model'];
            $this->validateModelClass($modelClass);

            /** @var Shape $shape */
            $shape = $queryDef['shape'];
            $query = $modelClass::query();

            // Select fields
            $fields = $shape->getFields();
            if ($fields !== ['*']) {
                $primaryKey = (new $modelClass())->getKeyName();
                if (!in_array($primaryKey, $fields)) {
                    array_unshift($fields, $primaryKey);
                }
                $query->select($fields);
            }

            // Eager load
            $relations = $this->extractRelations($shape);
            if (!empty($relations)) {
                $query->with($this->buildEagerLoadForShape($shape));
            }

            // Apply constraints
            $this->applyConstraints($query, $shape->getConstraints());

            // Apply scope
            if ($scope = $shape->getScope()) {
                $scope($query, $this->resolved);
            }

            // Order
            foreach ($shape->getOrderBy() as [$column, $dir]) {
                $query->orderBy($column, $dir);
            }

            // Pagination or limit/offset
            if (!empty($queryDef['paginate'])) {
                /** @var int $perPage */
                $perPage = $queryDef['perPage'];
                /** @var int|null $page */
                $page = $queryDef['page'] ?? null;
                $result = $query->paginate($perPage, ['*'], 'page', $page);
                $this->resolved[$alias] = new PaginatedResult($result);
            } else {
                if ($limit = $shape->getLimit()) {
                    $query->limit($limit);
                }
                if ($offset = $shape->getOffset()) {
                    $query->offset($offset);
                }

                $results = $query->get();

                if ($shape->shouldReturnArray()) {
                    $results = $results->map->toArray();
                }

                $this->resolved[$alias] = new DataSet($results, $results->count());
            }

            $this->metrics['queries']++;
        }
    }

    protected function resolveAggregates(): void
    {
        $aggregates = $this->requirements->getAggregates();
        /** @var array<class-string<Model>, array<string, array<string, mixed>>> $byModel */
        $byModel = [];

        // Group by model for potential batching
        foreach ($aggregates as $alias => $agg) {
            if (isset($this->resolved[$alias])) {
                continue;
            }

            /** @var class-string<Model> $model */
            $model = $agg['model'];
            $byModel[$model] ??= [];
            $byModel[$model][$alias] = $agg;
        }

        foreach ($byModel as $modelClass => $modelAggs) {
            // Check if all have same constraints (batchable)
            if ($this->canBatchAggregates($modelAggs)) {
                $this->batchResolveAggregates($modelClass, $modelAggs);
            } else {
                foreach ($modelAggs as $alias => $agg) {
                    $this->resolveSingleAggregate($alias, $agg);
                }
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $aggregates
     */
    protected function canBatchAggregates(array $aggregates): bool
    {
        /** @var array<int, array<string, mixed>>|null $first */
        $first = null;
        foreach ($aggregates as $agg) {
            /** @var Shape $shape */
            $shape = $agg['shape'];
            $constraints = $shape->getConstraints();
            if ($first === null) {
                $first = $constraints;
            } elseif ($constraints !== $first) {
                return false;
            }
        }
        return count($aggregates) > 1;
    }

    /**
     * Allowed aggregate function types (whitelist for SQL injection prevention)
     */
    protected const ALLOWED_AGGREGATE_TYPES = ['count', 'sum', 'avg', 'min', 'max'];

    /**
     * @param class-string<Model> $modelClass
     * @param array<string, array<string, mixed>> $aggregates
     */
    protected function batchResolveAggregates(string $modelClass, array $aggregates): void
    {
        $selects = [];
        $first = reset($aggregates);
        if ($first === false) {
            return;
        }

        foreach ($aggregates as $alias => $agg) {
            if (!is_string($agg['type']) || !is_string($agg['column'])) {
                throw new \InvalidArgumentException('Aggregate type and column must be strings');
            }

            $type = strtolower($agg['type']);
            $column = $agg['column'];

            // Validate aggregate type against whitelist
            if (!in_array($type, self::ALLOWED_AGGREGATE_TYPES, true)) {
                throw new \InvalidArgumentException("Invalid aggregate type: {$type}. Allowed: " . implode(', ', self::ALLOWED_AGGREGATE_TYPES));
            }

            // Validate column name (alphanumeric, underscores, dots, or * for COUNT)
            if ($column !== '*' && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $column)) {
                throw new \InvalidArgumentException("Invalid column name: {$column}");
            }

            // Validate alias (alphanumeric and underscores only)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
                throw new \InvalidArgumentException("Invalid alias name: {$alias}");
            }

            // Don't quote * for COUNT(*)
            $columnExpr = $column === '*' ? '*' : "`{$column}`";
            $selects[] = DB::raw("{$type}({$columnExpr}) as `{$alias}`");
        }

        $query = $modelClass::query()->select($selects);
        /** @var Shape $firstShape */
        $firstShape = $first['shape'];
        $this->applyConstraints($query, $firstShape->getConstraints());

        $result = $query->first();
        $this->metrics['queries']++;

        foreach ($aggregates as $alias => $agg) {
            $this->resolved[$alias] = $result->{$alias} ?? 0;
        }
    }

    /**
     * @param array<string, mixed> $agg
     */
    protected function resolveSingleAggregate(string $alias, array $agg): void
    {
        if (!is_string($agg['type']) || !is_string($agg['column'])) {
            throw new \InvalidArgumentException('Aggregate type and column must be strings');
        }

        $type = strtolower($agg['type']);
        $column = $agg['column'];

        // Validate aggregate type against whitelist
        if (!in_array($type, self::ALLOWED_AGGREGATE_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid aggregate type: {$type}. Allowed: " . implode(', ', self::ALLOWED_AGGREGATE_TYPES));
        }

        // Validate column name (alphanumeric, underscores, dots, or * for COUNT)
        if ($column !== '*' && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $agg['model'];
        /** @var Shape $shape */
        $shape = $agg['shape'];

        $query = $modelClass::query();
        $this->applyConstraints($query, $shape->getConstraints());

        $this->resolved[$alias] = $query->{$type}($column);
        $this->metrics['queries']++;
    }

    protected function resolveRaw(): void
    {
        foreach ($this->requirements->getRaw() as $alias => $raw) {
            if (isset($this->resolved[$alias])) {
                continue;
            }

            /** @var string $sql */
            $sql = $raw['sql'];
            /** @var array<int, mixed> $bindings */
            $bindings = $raw['bindings'];
            $results = DB::select($sql, $bindings);
            $this->resolved[$alias] = new DataSet($results, count($results));
            $this->metrics['queries']++;
        }
    }

    protected function resolveComputed(): void
    {
        $computed = $this->requirements->getComputed();
        $resolved = [];
        $maxIterations = count($computed) * 2; // Prevent infinite loops
        $iterations = 0;

        while (count($resolved) < count($computed) && $iterations++ < $maxIterations) {
            foreach ($computed as $alias => $comp) {
                if (isset($resolved[$alias])) {
                    continue;
                }

                // Check dependencies
                $canResolve = true;
                /** @var array<int, string> $depends */
                $depends = $comp['depends'];
                foreach ($depends as $dep) {
                    if (!array_key_exists($dep, $this->resolved)) {
                        $canResolve = false;
                        break;
                    }
                }

                if ($canResolve) {
                    /** @var callable(array<string, mixed>): mixed $computer */
                    $computer = $comp['computer'];
                    $this->resolved[$alias] = $computer($this->resolved);
                    $resolved[$alias] = true;
                }
            }
        }
    }

    protected function applyPresenters(): void
    {
        if (!$this->presenter || !$this->config->get('presenter.enabled')) {
            return;
        }

        $entities = $this->requirements->getEntities();
        $queries = $this->requirements->getQueries();

        foreach (array_merge($entities, $queries) as $alias => $def) {
            if (!isset($this->resolved[$alias])) {
                continue;
            }

            /** @var Shape $shape */
            $shape = $def['shape'];
            $presenterClass = $shape->getPresenter();
            if (!$presenterClass) {
                continue;
            }

            $data = $this->resolved[$alias];

            if ($data instanceof DataSet) {
                $this->resolved[$alias] = $data->map(
                    fn($item) => $item instanceof Model
                        ? $this->presenter->present($item, $presenterClass)
                        : $item,
                );
            } elseif ($data instanceof Model) {
                $this->resolved[$alias] = $this->presenter->present($data, $presenterClass);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function extractRelations(Shape $shape): array
    {
        $relations = [];

        foreach ($shape->getRelations() as $relation => $nestedShape) {
            $relations[] = $relation;

            if ($nestedShape instanceof Shape) {
                foreach ($this->extractRelations($nestedShape) as $nested) {
                    $relations[] = "{$relation}.{$nested}";
                }
            }
        }

        return $relations;
    }

    /**
     * @param array<string, array<string, mixed>> $entities
     * @return array<int|string, \Closure|string>
     */
    protected function buildEagerLoads(array $entities): array
    {
        $loads = [];

        foreach ($entities as $entity) {
            /** @var Shape $shape */
            $shape = $entity['shape'];
            $loads = array_merge($loads, $this->buildEagerLoadForShape($shape));
        }

        return $loads;
    }

    /**
     * @return array<int|string, \Closure|string>
     */
    protected function buildEagerLoadForShape(Shape $shape): array
    {
        $loads = [];

        foreach ($shape->getRelations() as $relation => $nestedShape) {
            if ($nestedShape instanceof Shape) {
                $loads[$relation] = function ($query) use ($nestedShape): void {
                    $fields = $nestedShape->getFields();
                    if ($fields !== ['*']) {
                        $query->select($fields);
                    }

                    $this->applyConstraints($query, $nestedShape->getConstraints());

                    foreach ($nestedShape->getOrderBy() as [$col, $dir]) {
                        $query->orderBy($col, $dir);
                    }

                    if ($limit = $nestedShape->getLimit()) {
                        $query->limit($limit);
                    }

                    // Nested relations
                    $nested = $this->buildEagerLoadForShape($nestedShape);
                    if (!empty($nested)) {
                        $query->with($nested);
                    }
                };
            } elseif (is_callable($nestedShape)) {
                $loads[$relation] = \Closure::fromCallable($nestedShape);
            } else {
                $loads[] = $relation;
            }
        }

        return $loads;
    }

    /**
     * @param array<string, array<string, mixed>> $entities
     * @return array<int, string>
     */
    protected function collectFields(array $entities): array
    {
        $fields = [];

        foreach ($entities as $entity) {
            /** @var Shape $shape */
            $shape = $entity['shape'];
            $shapeFields = $shape->getFields();
            if ($shapeFields === ['*']) {
                return ['*'];
            }
            $fields = array_merge($fields, $shapeFields);
        }

        return array_values(array_unique($fields));
    }

    /**
     * @param Builder<Model>|\Illuminate\Database\Eloquent\Relations\Relation<Model, Model, mixed> $query
     * @param array<int, array<string, mixed>> $constraints
     */
    protected function applyConstraints(Builder|\Illuminate\Database\Eloquent\Relations\Relation $query, array $constraints): void
    {
        foreach ($constraints as $c) {
            /** @var string $type */
            $type = $c['type'];

            switch ($type) {
                case 'basic':
                    /** @var string $column */
                    $column = $c['column'];
                    /** @var string $operator */
                    $operator = $c['operator'];
                    $query->where($column, $operator, $this->resolveValue($c['value']));
                    break;
                case 'in':
                    /** @var string $column */
                    $column = $c['column'];
                    /** @var array<int, mixed> $values */
                    $values = $this->resolveValue($c['values']);
                    $query->whereIn($column, $values);
                    break;
                case 'notIn':
                    /** @var string $column */
                    $column = $c['column'];
                    /** @var array<int, mixed> $values */
                    $values = $this->resolveValue($c['values']);
                    $query->whereNotIn($column, $values);
                    break;
                case 'between':
                    /** @var string $column */
                    $column = $c['column'];
                    /** @var array{0: mixed, 1: mixed} $range */
                    $range = $c['range'];
                    $query->whereBetween($column, $range);
                    break;
                case 'null':
                    /** @var string $column */
                    $column = $c['column'];
                    $query->whereNull($column);
                    break;
                case 'notNull':
                    /** @var string $column */
                    $column = $c['column'];
                    $query->whereNotNull($column);
                    break;
                case 'has':
                    /** @var string $relation */
                    $relation = $c['relation'];
                    $callback = isset($c['callback']) && is_callable($c['callback'])
                        ? \Closure::fromCallable($c['callback'])
                        : null;
                    $query->whereHas($relation, $callback);
                    break;
                case 'doesntHave':
                    /** @var string $relation */
                    $relation = $c['relation'];
                    $callback = isset($c['callback']) && is_callable($c['callback'])
                        ? \Closure::fromCallable($c['callback'])
                        : null;
                    $query->whereDoesntHave($relation, $callback);
                    break;
                case 'raw':
                    /** @var string $sql */
                    $sql = $c['sql'];
                    /** @var array<int, mixed> $bindings */
                    $bindings = $c['bindings'] ?? [];
                    $query->whereRaw($sql, $bindings);
                    break;
            }
        }
    }

    protected function resolveValue(mixed $value): mixed
    {
        return is_callable($value) ? $value($this->resolved) : $value;
    }

    /**
     * Validate that a model class exists and extends Eloquent Model
     *
     * @throws \InvalidArgumentException if model class is invalid
     */
    protected function validateModelClass(string $modelClass): void
    {
        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class does not exist: {$modelClass}");
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("Class is not an Eloquent Model: {$modelClass}");
        }
    }
}
