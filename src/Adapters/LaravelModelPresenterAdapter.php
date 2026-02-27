<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Adapters;

use AdroSoftware\DataProxy\Contracts\PresenterAdapterInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Adapter for adrorocker/laravel-model-presenter
 * Install: composer require adrorocker/laravel-model-presenter
 */
final class LaravelModelPresenterAdapter implements PresenterAdapterInterface
{
    protected string $namespace;
    protected string $suffix;

    /** @var array<class-string<Model>, class-string> */
    protected array $map = [];

    public function __construct(
        string $namespace = 'App\\Presenters\\',
        string $suffix = 'Presenter',
    ) {
        $this->namespace = $namespace;
        $this->suffix = $suffix;
    }

    /**
     * Register a specific model-presenter mapping
     *
     * @param class-string<Model> $modelClass
     * @param class-string $presenterClass
     */
    public function register(string $modelClass, string $presenterClass): self
    {
        $this->map[$modelClass] = $presenterClass;
        return $this;
    }

    /**
     * Register multiple mappings at once
     *
     * @param array<class-string<Model>, class-string> $mappings
     */
    public function registerMany(array $mappings): self
    {
        foreach ($mappings as $modelClass => $presenterClass) {
            $this->map[$modelClass] = $presenterClass;
        }
        return $this;
    }

    public function present(Model $model, ?string $presenterClass = null): mixed
    {
        $presenterClass ??= $this->resolvePresenter($model);

        if (!$presenterClass) {
            return $model;
        }

        // Validate presenter class exists before instantiation
        if (!class_exists($presenterClass)) {
            throw new \InvalidArgumentException("Presenter class does not exist: {$presenterClass}");
        }

        // Check if model uses the Presentable trait from the package
        if (method_exists($model, 'present')) {
            return $model->present($presenterClass);
        }

        // Manual presenter instantiation
        return new $presenterClass($model);
    }

    public function hasPresenter(Model $model): bool
    {
        return $this->resolvePresenter($model) !== null;
    }

    public function resolvePresenter(Model $model): ?string
    {
        $modelClass = get_class($model);

        if (isset($this->map[$modelClass])) {
            return $this->map[$modelClass];
        }

        // Auto-discover: App\Models\User -> App\Presenters\UserPresenter
        $baseName = class_basename($modelClass);
        $presenterClass = $this->namespace . $baseName . $this->suffix;

        if (class_exists($presenterClass)) {
            $this->map[$modelClass] = $presenterClass;
            return $presenterClass;
        }

        return null;
    }

    /**
     * Set the namespace for auto-discovery
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Set the suffix for auto-discovery
     */
    public function setSuffix(string $suffix): self
    {
        $this->suffix = $suffix;
        return $this;
    }
}
