<?php

namespace Abdulsalam\LaravelContextFlow\Tests\Feature;

use Abdulsalam\LaravelContextFlow\ContextFlowManager;
use Abdulsalam\LaravelContextFlow\Enums\BoundaryType;
use Abdulsalam\LaravelContextFlow\Enums\PropagationTarget;
use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;
use Abdulsalam\LaravelContextFlow\Support\ContextKeys;
use Abdulsalam\LaravelContextFlow\Tests\TestCase;
use Illuminate\Support\Facades\Context;

final class ContextFlowManagerTest extends TestCase
{
    public function test_it_starts_a_root_context(): void
    {
        $flow = app(ContextFlowManager::class);
        $flow->startRoot(BoundaryType::Http);

        self::assertNotNull($flow->correlationId());
        self::assertNotNull($flow->executionId());
        self::assertNotNull($flow->requestId());
        self::assertSame('http', Context::get(ContextKeys::BOUNDARY));
    }

    public function test_rotation_preserves_correlation_and_sets_causation(): void
    {
        $flow = app(ContextFlowManager::class);
        $flow->startRoot(BoundaryType::Http);
        $correlation = $flow->correlationId();
        $oldExecution = $flow->executionId();

        $flow->rotateExecution(BoundaryType::Queue);

        self::assertSame($correlation, $flow->correlationId());
        self::assertSame($oldExecution, $flow->causationId());
        self::assertNotSame($oldExecution, $flow->executionId());
        self::assertSame('http', Context::get(ContextKeys::ORIGIN));
        self::assertSame('queue', Context::get(ContextKeys::BOUNDARY));
        self::assertSame(TrustLevel::Local->value, Context::get(ContextKeys::TRUST));
    }

    public function test_http_snapshot_only_contains_allowlisted_business_keys(): void
    {
        $flow = app(ContextFlowManager::class);
        $flow->startRoot(BoundaryType::Http);
        Context::add(['tenant_id' => 7, 'password' => 'secret', 'random' => 'x']);

        $snapshot = $flow->snapshot(PropagationTarget::InternalHttp, TrustLevel::Internal);

        self::assertSame(7, $snapshot->values['tenant_id']);
        self::assertArrayNotHasKey('password', $snapshot->values);
        self::assertArrayNotHasKey('random', $snapshot->values);
    }

    public function test_registered_hidden_value_can_propagate_when_policy_allows_it(): void
    {
        $flow = app(ContextFlowManager::class);
        $flow->startRoot(BoundaryType::Http);
        Context::addHidden('tenant_id', 44);

        $snapshot = $flow->snapshot(PropagationTarget::InternalHttp, TrustLevel::Internal);

        self::assertSame(44, $snapshot->values['tenant_id']);
    }

    public function test_root_boundary_clears_registered_hidden_values_from_previous_execution(): void
    {
        $flow = app(ContextFlowManager::class);
        Context::addHidden('tenant_id', 999);

        $flow->startRoot(BoundaryType::Http);

        self::assertFalse(Context::hasHidden('tenant_id'));
    }

    public function test_without_propagation_returns_empty_snapshot(): void
    {
        $flow = app(ContextFlowManager::class);
        $flow->startRoot(BoundaryType::Http);

        $snapshot = $flow->withoutPropagation(
            fn () => $flow->snapshot(PropagationTarget::InternalHttp, TrustLevel::Internal),
        );

        self::assertSame([], $snapshot->values);
    }
}
