<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

/**
 * Define all data requirements - the "query document"
 */
class Requirements
{
    protected array $entities = [];
    protected array $queries = [];
    protected array $aggregates = [];
    protected array $computed = [];
    protected array $raw = [];
    protected array $cache = [];

    public static function make(): static
    {
        return new static();
    }

    /**
     * Need a single entity by ID
     */
    public function one(string $alias, string $model, int|string|callable $id, ?Shape $shape = null): static
    {
        $this->entities[$alias] = [
            'type' => 'one',
            'model' => $model,
            'id' => $id,
            'shape' => $shape ?? Shape::make(),
        ];
        return $this;
    }

    /**
     * Need multiple entities by IDs
     */
    public function many(string $alias, string $model, array|callable $ids, ?Shape $shape = null): static
    {
        $this->entities[$alias] = [
            'type' => 'many',
            'model' => $model,
            'ids' => $ids,
            'shape' => $shape ?? Shape::make(),
        ];
        return $this;
    }

    /**
     * Need entities matching criteria
     */
    public function query(string $alias, string $model, ?Shape $shape = null): static
    {
        $this->queries[$alias] = [
            'model' => $model,
            'shape' => $shape ?? Shape::make(),
        ];
        return $this;
    }

    /**
     * Need paginated results
     */
    public function paginate(
        string $alias,
        string $model,
        int $perPage = 15,
        ?int $page = null,
        ?Shape $shape = null
    ): static {
        $this->queries[$alias] = [
            'model' => $model,
            'shape' => $shape ?? Shape::make(),
            'paginate' => true,
            'perPage' => $perPage,
            'page' => $page,
        ];
        return $this;
    }

    /**
     * Aggregate: count
     */
    public function count(string $alias, string $model, ?Shape $shape = null): static
    {
        $this->aggregates[$alias] = [
            'type' => 'count',
            'model' => $model,
            'column' => '*',
            'shape' => $shape ?? Shape::make(),
        ];
        return $this;
    }

    /**
     * Aggregate: sum
     */
    public function sum(string $alias, string $model, string $column, ?Shape $shape = null): static
    {
        $this->aggregates[$alias] = [
            'type' => 'sum',
            'model' => $model,
            'column' => $column,
            'shape' => $shape ?? Shape::make(),
        ];
        return $this;
    }

    /**
     * Aggregate: avg
     */
    public function avg(string $alias, string $model, string $column, ?Shape $shape = null): static
    {
        $this->aggregates[$alias] = [
            'type' => 'avg',
            'model' => $model,
            'column' => $column,
            'shape' => $shape ?? Shape::make(),
        ];
        return $this;
    }

    /**
     * Aggregate: min
     */
    public function min(string $alias, string $model, string $column, ?Shape $shape = null): static
    {
        $this->aggregates[$alias] = [
            'type' => 'min',
            'model' => $model,
            'column' => $column,
            'shape' => $shape ?? Shape::make(),
        ];
        return $this;
    }

    /**
     * Aggregate: max
     */
    public function max(string $alias, string $model, string $column, ?Shape $shape = null): static
    {
        $this->aggregates[$alias] = [
            'type' => 'max',
            'model' => $model,
            'column' => $column,
            'shape' => $shape ?? Shape::make(),
        ];
        return $this;
    }

    /**
     * Raw SQL query
     */
    public function raw(string $alias, string $sql, array $bindings = []): static
    {
        $this->raw[$alias] = [
            'sql' => $sql,
            'bindings' => $bindings,
        ];
        return $this;
    }

    /**
     * Computed value from other resolved data
     */
    public function compute(string $alias, callable $computer, array $dependsOn = []): static
    {
        $this->computed[$alias] = [
            'computer' => $computer,
            'depends' => $dependsOn,
        ];
        return $this;
    }

    /**
     * Cache a specific requirement
     */
    public function cache(string $alias, string $key, ?int $ttl = null, array $tags = []): static
    {
        $this->cache[$alias] = [
            'key' => $key,
            'ttl' => $ttl,
            'tags' => $tags,
        ];
        return $this;
    }

    // Getters

    public function getEntities(): array
    {
        return $this->entities;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getAggregates(): array
    {
        return $this->aggregates;
    }

    public function getComputed(): array
    {
        return $this->computed;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    public function getCache(): array
    {
        return $this->cache;
    }

    public function all(): array
    {
        return [
            'entities' => $this->entities,
            'queries' => $this->queries,
            'aggregates' => $this->aggregates,
            'computed' => $this->computed,
            'raw' => $this->raw,
        ];
    }
}
