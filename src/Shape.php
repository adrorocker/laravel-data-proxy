<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy;

/**
 * Describes the shape of data - like a GraphQL query
 */
class Shape
{
    protected array $fields = ['*'];
    protected array $relations = [];
    protected array $constraints = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $orderBy = [];
    /** @var callable|null */
    protected $scope = null;
    protected ?string $presenter = null;
    protected bool $asArray = false;

    public static function make(): static
    {
        return new static();
    }

    /**
     * Select specific fields
     */
    public function select(string|array ...$fields): static
    {
        $this->fields = is_array($fields[0] ?? null) ? $fields[0] : $fields;
        return $this;
    }

    /**
     * Include a relation with optional nested shape
     */
    public function with(string|array $relation, Shape|callable|null $shape = null): static
    {
        if (is_array($relation)) {
            foreach ($relation as $rel => $s) {
                if (is_int($rel)) {
                    $this->relations[$s] = null;
                } else {
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
    public function where(string $column, mixed $operator = null, mixed $value = null): static
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
     */
    public function whereIn(string $column, array|callable $values): static
    {
        $this->constraints[] = ['type' => 'in', 'column' => $column, 'values' => $values];
        return $this;
    }

    /**
     * Add a whereNotIn constraint
     */
    public function whereNotIn(string $column, array $values): static
    {
        $this->constraints[] = ['type' => 'notIn', 'column' => $column, 'values' => $values];
        return $this;
    }

    /**
     * Add a whereBetween constraint
     */
    public function whereBetween(string $column, array $range): static
    {
        $this->constraints[] = ['type' => 'between', 'column' => $column, 'range' => $range];
        return $this;
    }

    /**
     * Add a whereNull constraint
     */
    public function whereNull(string $column): static
    {
        $this->constraints[] = ['type' => 'null', 'column' => $column];
        return $this;
    }

    /**
     * Add a whereNotNull constraint
     */
    public function whereNotNull(string $column): static
    {
        $this->constraints[] = ['type' => 'notNull', 'column' => $column];
        return $this;
    }

    /**
     * Add a whereHas constraint
     */
    public function whereHas(string $relation, ?callable $callback = null): static
    {
        $this->constraints[] = ['type' => 'has', 'relation' => $relation, 'callback' => $callback];
        return $this;
    }

    /**
     * Add a whereDoesntHave constraint
     */
    public function whereDoesntHave(string $relation, ?callable $callback = null): static
    {
        $this->constraints[] = ['type' => 'doesntHave', 'relation' => $relation, 'callback' => $callback];
        return $this;
    }

    /**
     * Add a whereRaw constraint
     */
    public function whereRaw(string $sql, array $bindings = []): static
    {
        $this->constraints[] = ['type' => 'raw', 'sql' => $sql, 'bindings' => $bindings];
        return $this;
    }

    /**
     * Conditional constraint
     */
    public function when(bool $condition, callable $callback): static
    {
        if ($condition) {
            $callback($this);
        }
        return $this;
    }

    /**
     * Apply a custom query scope
     */
    public function scope(callable $scope): static
    {
        $this->scope = $scope;
        return $this;
    }

    /**
     * Add ordering
     */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->orderBy[] = [$column, $direction];
        return $this;
    }

    /**
     * Order by latest (descending)
     */
    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Order by oldest (ascending)
     */
    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Limit results
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Offset results
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Alias for limit
     */
    public function take(int $count): static
    {
        return $this->limit($count);
    }

    /**
     * Alias for offset
     */
    public function skip(int $count): static
    {
        return $this->offset($count);
    }

    /**
     * Apply a presenter to results
     */
    public function present(string $presenterClass): static
    {
        $this->presenter = $presenterClass;
        return $this;
    }

    /**
     * Return as plain arrays instead of models
     */
    public function asArray(): static
    {
        $this->asArray = true;
        return $this;
    }

    // Getters

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

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
}
