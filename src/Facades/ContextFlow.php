<?php

namespace Abdulsalam\LaravelContextFlow\Facades;

use Abdulsalam\LaravelContextFlow\ContextFlowManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ?string correlationId()
 * @method static ?string executionId()
 * @method static ?string causationId()
 * @method static ?string requestId()
 * @method static mixed withoutPropagation(callable $callback)
 * @method static mixed only(array $keys, callable $callback)
 * @method static \Abdulsalam\LaravelContextFlow\Context\ContextSnapshot snapshotRaw()
 * @see \Abdulsalam\LaravelContextFlow\ContextFlowManager
 */
final class ContextFlow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ContextFlowManager::class;
    }
}
