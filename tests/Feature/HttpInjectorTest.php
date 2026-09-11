<?php

namespace Abdulsalam\LaravelContextFlow\Tests\Feature;

use Abdulsalam\LaravelContextFlow\ContextFlowManager;
use Abdulsalam\LaravelContextFlow\Enums\BoundaryType;
use Abdulsalam\LaravelContextFlow\Http\HttpContextInjector;
use Abdulsalam\LaravelContextFlow\Tests\TestCase;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Context;

final class HttpInjectorTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('context-flow.http.trusted_hosts', ['*.internal']);
        $app['config']->set('context-flow.http.outgoing', true);
        $app['config']->set('context-flow.security.sign_internal_context', true);
    }

    public function test_it_propagates_business_context_only_to_internal_hosts(): void
    {
        $flow = app(ContextFlowManager::class);
        $flow->startRoot(BoundaryType::Http);
        Context::add(['tenant_id' => 77, 'actor_id' => 9]);

        $injector = app(HttpContextInjector::class);
        $internal = $injector(new Request('POST', 'https://wallet.internal/pay'));
        $external = $injector(new Request('POST', 'https://api.example.com/pay'));

        self::assertStringContainsString('tenant_id=77', $internal->getHeaderLine('baggage'));
        self::assertSame('', $external->getHeaderLine('baggage'));
        self::assertSame('', $external->getHeaderLine('X-Correlation-ID'));
        self::assertNotSame('', $internal->getHeaderLine('X-Correlation-ID'));
        self::assertNotSame('', $internal->getHeaderLine('X-Context-Signature'));
    }
}
