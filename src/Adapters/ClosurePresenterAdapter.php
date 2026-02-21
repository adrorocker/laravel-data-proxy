<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Adapters;

use AdroSoftware\DataProxy\Contracts\PresenterAdapterInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic presenter adapter using closures
 * For when you don't want to use a presenter package
 */
class ClosurePresenterAdapter implements PresenterAdapterInterface
{
    protected array $presenters = [];

    /**
     * Register a presenter closure for a model
     */
    public function register(string $modelClass, callable $presenter): static
    {
        $this->presenters[$modelClass] = $presenter;
        return $this;
    }

    /**
     * Register multiple presenter closures at once
     */
    public function registerMany(array $presenters): static
    {
        foreach ($presenters as $modelClass => $presenter) {
            $this->presenters[$modelClass] = $presenter;
        }
        return $this;
    }

    public function present(Model $model, ?string $presenterClass = null): mixed
    {
        $modelClass = get_class($model);

        if (isset($this->presenters[$modelClass])) {
            return ($this->presenters[$modelClass])($model);
        }

        return $model;
    }

    public function hasPresenter(Model $model): bool
    {
        return isset($this->presenters[get_class($model)]);
    }

    public function resolvePresenter(Model $model): ?string
    {
        return isset($this->presenters[get_class($model)]) ? 'closure' : null;
    }
}
