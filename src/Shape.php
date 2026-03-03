<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

/**
 * Describes the shape of data - like a GraphQL query
 */
final class Shape
{
    /** @var array<int, string> */
    protected array $fields = ['*'];

    /** @var array<string, Shape|callable|null> */
    protected array $relations = [];

    /** @var array<int, array<string, mixed>> */
    protected array $constraints = [];

    protected ?int $limit = null;
    protected ?int $offset = null;

    /** @var array<int, array{0: string, 1: string}> */
    protected array $orderBy = [];

    /** @var callable|null */
    protected $scope = null;
    protected ?string $presenter = null;
    protected bool $asArray = false;

    /** @var callable|null */
    protected $hydrate = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * Select specific fields
     *
     * @param string|array<int, string> ...$fields
     */
    /**
     * @param array<int, string> $fields
     */
    public function select(string|array ...$fields): self
    {
        /** @var array<int, string> $resolved */
        $resolved = is_array($fields[0] ?? null) ? $fields[0] : $fields;
        $this->fields = $resolved;
        return $this;
    }

    /**
     * Include a relation with optional nested shape
     *
     * @param string|array<int|string, Shape|callable|null|string> $relation
     */
    public function with(string|array $relation, Shape|callable|null $shape = null): self
    {
        if (is_array($relation)) {
            foreach ($relation as $rel => $s) {
                if (is_int($rel)) {
                    // Numeric key means value is the relation name
                    assert(is_string($s));
                    $this->relations[$s] = null;
                } else {
                    // String key is relation name, value is shape/callable/null
                    assert($s instanceof Shape || is_callable($s) || $s === null);
                    $this->relations[$rel] = $s;
                }
            }
        } else {
            $this->relations[$relation] = $shape;
        }
        return $this;
    }

    /**
     * Add a where constraint
     */
    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $this->constraints[] = ['type' => 'basic', 'column' => $column, 'operator' => '=', 'value' => $operator];
        } else {
            $this->constraints[] = ['type' => 'basic', 'column' => $column, 'operator' => $operator, 'value' => $value];
        }
        return $this;
    }

    /**
     * Add a whereIn constraint
     *
     * @param array<int, mixed>|callable $values
     */
    public function whereIn(string $column, array|callable $values): self
    {
        $this->constraints[] = ['type' => 'in', 'column' => $column, 'values' => $values];
        return $this;
    }

    /**
     * Add a whereNotIn constraint
     *
     * @param array<int, mixed> $values
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->constraints[] = ['type' => 'notIn', 'column' => $column, 'values' => $values];
        return $this;
    }

    /**
     * Add a whereBetween constraint
     *
     * @param array{0: mixed, 1: mixed} $range
     */
    public function whereBetween(string $column, array $range): self
    {
        $this->constraints[] = ['type' => 'between', 'column' => $column, 'range' => $range];
        return $this;
    }

    /**
     * Add a whereNull constraint
     */
    public function whereNull(string $column): self
    {
        $this->constraints[] = ['type' => 'null', 'column' => $column];
        return $this;
    }

    /**
     * Add a whereNotNull constraint
     */
    public function whereNotNull(string $column): self
    {
        $this->constraints[] = ['type' => 'notNull', 'column' => $column];
        return $this;
    }

    /**
     * Add a whereHas constraint
     */
    public function whereHas(string $relation, ?callable $callback = null): self
    {
        $this->constraints[] = ['type' => 'has', 'relation' => $relation, 'callback' => $callback];
        return $this;
    }

    /**
     * Add a whereDoesntHave constraint
     */
    public function whereDoesntHave(string $relation, ?callable $callback = null): self
    {
        $this->constraints[] = ['type' => 'doesntHave', 'relation' => $relation, 'callback' => $callback];
        return $this;
    }

    /**
     * Add a whereRaw constraint
     *
     * @param array<int, mixed> $bindings
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->constraints[] = ['type' => 'raw', 'sql' => $sql, 'bindings' => $bindings];
        return $this;
    }

    /**
     * Conditional constraint
     */
    public function when(bool $condition, callable $callback): self
    {
        if ($condition) {
            $callback($this);
        }
        return $this;
    }

    /**
     * Apply a custom query scope
     */
    public function scope(callable $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    /**
     * Add ordering
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orderBy[] = [$column, $direction];
        return $this;
    }

    /**
     * Order by latest (descending)
     */
    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Order by oldest (ascending)
     */
    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Limit results
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Offset results
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Alias for limit
     */
    public function take(int $count): self
    {
        return $this->limit($count);
    }

    /**
     * Alias for offset
     */
    public function skip(int $count): self
    {
        return $this->offset($count);
    }

    /**
     * Apply a presenter to results
     */
    public function present(string $presenterClass): self
    {
        $this->presenter = $presenterClass;
        return $this;
    }

    /**
     * Return as plain arrays instead of models
     */
    public function asArray(): self
    {
        $this->asArray = true;
        return $this;
    }

    // Getters

    /**
     * @return array<int, string>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, Shape|callable|null>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getScope(): ?callable
    {
        return $this->scope;
    }

    public function getPresenter(): ?string
    {
        return $this->presenter;
    }

    public function shouldReturnArray(): bool
    {
        return $this->asArray;
    }

    /**
     * Register a hydration callback to batch-load data after query execution.
     * Callback receives (Collection $items, array $resolved) and should mutate items in place.
     */
    public function hydrate(callable $callback): self
    {
        $this->hydrate = $callback;
        return $this;
    }

    public function getHydrate(): ?callable
    {
        return $this->hydrate;
    }
}
