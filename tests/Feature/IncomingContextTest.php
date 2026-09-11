<?php

namespace Abdulsalam\LaravelContextFlow\Tests\Feature;

use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;
use Abdulsalam\LaravelContextFlow\Http\BaggageCodec;
use Abdulsalam\LaravelContextFlow\Http\Middleware\CaptureIncomingContext;
use Abdulsalam\LaravelContextFlow\Security\ContextSigner;
use Abdulsalam\LaravelContextFlow\Support\ContextKeys;
use Abdulsalam\LaravelContextFlow\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

final class IncomingContextTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('context-flow.http.incoming', true);
        $app['config']->set('context-flow.security.sign_internal_context', true);
    }

    public function test_signed_internal_baggage_is_accepted(): void
    {
        $codec = app(BaggageCodec::class);
        $signer = app(ContextSigner::class);
        $baggage = $codec->encode(['tenant_id' => '7', 'actor_id' => '9']);
        $timestamp = time();
        $signature = $signer->sign('c-1', 'e-1', $baggage, $timestamp);

        $request = Request::create('/withdraw', 'POST');
        $request->headers->set('X-Correlation-ID', 'c-1');
        $request->headers->set('X-Causation-ID', 'e-1');
        $request->headers->set('baggage', $baggage);
        $request->headers->set('X-Context-Timestamp', (string) $timestamp);
        $request->headers->set('X-Context-Signature', (string) $signature);

        app(CaptureIncomingContext::class)->handle($request, fn () => new Response('ok'));

        self::assertSame('7', Context::get('tenant_id'));
        self::assertSame('9', Context::get('actor_id'));
        self::assertSame(TrustLevel::Internal->value, Context::get(ContextKeys::TRUST));
        self::assertSame('e-1', Context::get(ContextKeys::CAUSATION_ID));
    }

    public function test_public_baggage_with_control_characters_is_rejected(): void
    {
        $request = Request::create('/withdraw', 'POST');
        $request->headers->set('baggage', 'locale=ar%0Aforged');

        app(CaptureIncomingContext::class)->handle($request, fn () => new Response('ok'));

        self::assertFalse(Context::has('locale'));
    }

    public function test_unsigned_public_request_cannot_inject_actor_or_tenant(): void
    {
        $request = Request::create('/withdraw', 'POST');
        $request->headers->set('baggage', 'actor_id=9,tenant_id=7,locale=ar');

        app(CaptureIncomingContext::class)->handle($request, fn () => new Response('ok'));

        self::assertFalse(Context::has('actor_id'));
        self::assertFalse(Context::has('tenant_id'));
        self::assertSame('ar', Context::get('locale'));
        self::assertSame(TrustLevel::Public->value, Context::get(ContextKeys::TRUST));
    }
}
