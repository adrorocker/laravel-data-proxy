<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface for presenter adapters
 * Implement this to integrate any presenter package
 */
interface PresenterAdapterInterface
{
    /**
     * Wrap a model with its presenter
     */
    public function present(Model $model, ?string $presenterClass = null): mixed;

    /**
     * Check if a model has a presenter
     */
    public function hasPresenter(Model $model): bool;

    /**
     * Resolve presenter class for a model
     */
    public function resolvePresenter(Model $model): ?string;
}
