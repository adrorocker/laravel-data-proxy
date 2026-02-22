<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Laravel;

use AdroSoftware\DataProxy\DataProxy;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Result;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Result fetch(Requirements $requirements)
 * @method static Result query(callable $builder)
 * @method static DataProxy withCache(\DataProxy\Contracts\CacheAdapterInterface $cache)
 * @method static DataProxy withoutCache()
 * @method static DataProxy withPresenter(\DataProxy\Contracts\PresenterAdapterInterface $presenter)
 * @method static DataProxy configure(array|callable $config)
 * @method static DataProxy forApi()
 * @method static DataProxy forExport()
 * @method static DataProxy forPerformance()
 *
 * @see DataProxy
 */
class DataProxyFacade extends Facade
{
    /**
     * Get the registered name of the component
     */
    protected static function getFacadeAccessor(): string
    {
        return DataProxy::class;
    }
}
