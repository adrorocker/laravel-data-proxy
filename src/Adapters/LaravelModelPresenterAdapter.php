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

    /** @var array<class-string<Model>, class-string|false> */
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
        // If explicit presenter class provided, validate and use it
        if ($presenterClass !== null) {
            if (!class_exists($presenterClass)) {
                throw new \InvalidArgumentException("Presenter class does not exist: {$presenterClass}");
            }
            return new $presenterClass($model);
        }

        // Check if model uses the PresentModel trait from adrosoftware/laravel-model-presenter
        // The trait's present() method takes no arguments and uses the model's $presenter property
        if (method_exists($model, 'present') && property_exists($model, 'presenter')) {
            return $model->present();
        }

        // Try to resolve via mapping or auto-discovery
        $resolvedClass = $this->resolvePresenter($model);

        if ($resolvedClass === null) {
            return $model;
        }

        if (!class_exists($resolvedClass)) {
            throw new \InvalidArgumentException("Presenter class does not exist: {$resolvedClass}");
        }

        // Manual presenter instantiation for models without the trait
        return new $resolvedClass($model);
    }

    public function hasPresenter(Model $model): bool
    {
        // Check if model uses the PresentModel trait
        if (method_exists($model, 'present') && property_exists($model, 'presenter')) {
            return true;
        }

        return $this->resolvePresenter($model) !== null;
    }

    public function resolvePresenter(Model $model): ?string
    {
        $modelClass = get_class($model);

        if (array_key_exists($modelClass, $this->map)) {
            $cached = $this->map[$modelClass];
            return $cached === false ? null : $cached;
        }

        // Auto-discover: App\Models\User -> App\Presenters\UserPresenter
        $baseName = class_basename($modelClass);
        $presenterClass = $this->namespace . $baseName . $this->suffix;

        if (class_exists($presenterClass)) {
            $this->map[$modelClass] = $presenterClass;
            return $presenterClass;
        }

        // Cache the miss so we don't re-run class_exists() on every call.
        $this->map[$modelClass] = false;
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
