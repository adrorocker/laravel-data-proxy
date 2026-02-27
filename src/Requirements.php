<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

/**
 * Define all data requirements - the "query document"
 */
final class Requirements
{
    /** @var array<string, array<string, mixed>> */
    protected array $entities = [];

    /** @var array<string, array<string, mixed>> */
    protected array $queries = [];

    /** @var array<string, array<string, mixed>> */
    protected array $aggregates = [];

    /** @var array<string, array<string, mixed>> */
    protected array $computed = [];

    /** @var array<string, array<string, mixed>> */
    protected array $raw = [];

    /** @var array<string, array<string, mixed>> */
    protected array $cache = [];

    public static function make(): self
    {
        return new self();
    }

    /**
     * Need a single entity by ID
     */
    public function one(string $alias, string $model, int|string|callable $id, ?Shape $shape = null): self
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
     *
     * @param array<int, int|string>|callable $ids
     */
    public function many(string $alias, string $model, array|callable $ids, ?Shape $shape = null): self
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
    public function query(string $alias, string $model, ?Shape $shape = null): self
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
        ?Shape $shape = null,
    ): self {
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
    public function count(string $alias, string $model, ?Shape $shape = null): self
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
    public function sum(string $alias, string $model, string $column, ?Shape $shape = null): self
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
    public function avg(string $alias, string $model, string $column, ?Shape $shape = null): self
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
    public function min(string $alias, string $model, string $column, ?Shape $shape = null): self
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
    public function max(string $alias, string $model, string $column, ?Shape $shape = null): self
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
     *
     * @param array<int, mixed> $bindings
     */
    public function raw(string $alias, string $sql, array $bindings = []): self
    {
        $this->raw[$alias] = [
            'sql' => $sql,
            'bindings' => $bindings,
        ];
        return $this;
    }

    /**
     * Computed value from other resolved data
     *
     * @param array<int, string> $dependsOn
     */
    public function compute(string $alias, callable $computer, array $dependsOn = []): self
    {
        $this->computed[$alias] = [
            'computer' => $computer,
            'depends' => $dependsOn,
        ];
        return $this;
    }

    /**
     * Cache a specific requirement
     *
     * @param array<int, string> $tags
     */
    public function cache(string $alias, string $key, ?int $ttl = null, array $tags = []): self
    {
        $this->cache[$alias] = [
            'key' => $key,
            'ttl' => $ttl,
            'tags' => $tags,
        ];
        return $this;
    }

    // Getters

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAggregates(): array
    {
        return $this->aggregates;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getComputed(): array
    {
        return $this->computed;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getCache(): array
    {
        return $this->cache;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
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
