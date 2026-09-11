<?php

namespace Abdulsalam\LaravelContextFlow\Tests\Feature;

use Abdulsalam\LaravelContextFlow\ContextFlowManager;
use Abdulsalam\LaravelContextFlow\Enums\BoundaryType;
use Abdulsalam\LaravelContextFlow\Queue\QueueContextBridge;
use Abdulsalam\LaravelContextFlow\Support\ContextKeys;
use Abdulsalam\LaravelContextFlow\Tests\TestCase;
use Illuminate\Log\Context\Repository;
use Illuminate\Support\Facades\Context;

final class QueueContextBridgeTest extends TestCase
{
    public function test_dehydration_filter_keeps_only_allowed_context(): void
    {
        $flow = app(ContextFlowManager::class);
        $flow->startRoot(BoundaryType::Http);

        $repository = new Repository(app('events'));
        $repository->add(Context::all());
        $repository->add(['tenant_id' => 5, 'random' => 'drop-me', 'api_token' => 'drop-me']);

        app(QueueContextBridge::class)->filterForDehydration($repository);

        self::assertTrue($repository->has(ContextKeys::CORRELATION_ID));
        self::assertTrue($repository->has('tenant_id'));
        self::assertFalse($repository->has('random'));
        self::assertFalse($repository->has('api_token'));
    }

    public function test_empty_hydrated_context_starts_one_fresh_queue_root(): void
    {
        Context::flush();
        $flow = app(ContextFlowManager::class);

        app(QueueContextBridge::class)->onHydrated(new Repository(app('events')));

        self::assertNotNull($flow->correlationId());
        self::assertNotNull($flow->executionId());
        self::assertNull($flow->causationId());
        self::assertSame('queue', Context::get(ContextKeys::ORIGIN));
        self::assertSame('queue', Context::get(ContextKeys::BOUNDARY));
    }

    public function test_hydrated_context_rotates_execution_id(): void
    {
        $flow = app(ContextFlowManager::class);
        $flow->startRoot(BoundaryType::Http);
        $old = $flow->executionId();

        app(QueueContextBridge::class)->onHydrated(new Repository(app('events')));

        self::assertSame($old, $flow->causationId());
        self::assertNotSame($old, $flow->executionId());
    }
}
