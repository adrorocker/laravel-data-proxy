<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Adapters;

use AdroSoftware\DataProxy\Contracts\PresenterAdapterInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic presenter adapter using closures
 * For when you don't want to use a presenter package
 */
final class ClosurePresenterAdapter implements PresenterAdapterInterface
{
    /** @var array<class-string<Model>, callable(Model): mixed> */
    protected array $presenters = [];

    /**
     * Register a presenter closure for a model
     *
     * @param class-string<Model> $modelClass
     * @param callable(Model): mixed $presenter
     */
    public function register(string $modelClass, callable $presenter): self
    {
        $this->presenters[$modelClass] = $presenter;
        return $this;
    }

    /**
     * Register multiple presenter closures at once
     *
     * @param array<class-string<Model>, callable(Model): mixed> $presenters
     */
    public function registerMany(array $presenters): self
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
