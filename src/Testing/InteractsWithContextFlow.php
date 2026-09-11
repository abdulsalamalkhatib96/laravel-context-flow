<?php

namespace Abdulsalam\LaravelContextFlow\Testing;

use Abdulsalam\LaravelContextFlow\ContextFlowManager;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Assert;

trait InteractsWithContextFlow
{
    protected function assertContextValue(string $key, mixed $expected): void
    {
        Assert::assertTrue(Context::has($key), "Context key [{$key}] is missing.");
        Assert::assertSame($expected, Context::get($key));
    }

    protected function assertContextMissing(string $key): void
    {
        Assert::assertFalse(Context::has($key), "Context key [{$key}] was unexpectedly present.");
    }

    protected function assertCorrelationIdExists(): void
    {
        Assert::assertNotNull(app(ContextFlowManager::class)->correlationId());
    }

    protected function assertExecutionIdExists(): void
    {
        Assert::assertNotNull(app(ContextFlowManager::class)->executionId());
    }
}
