<?php

namespace Abdulsalam\LaravelContextFlow\Tests\Unit;

use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;
use Abdulsalam\LaravelContextFlow\Policy\HostTrustResolver;
use PHPUnit\Framework\TestCase;

final class HostTrustResolverTest extends TestCase
{
    public function test_it_resolves_trusted_hosts(): void
    {
        $resolver = new HostTrustResolver(['*.internal', 'payments.local']);

        self::assertSame(TrustLevel::Internal, $resolver->resolve('https://wallet.internal/pay'));
        self::assertSame(TrustLevel::Internal, $resolver->resolve('http://payments.local/pay'));
        self::assertSame(TrustLevel::Public, $resolver->resolve('https://api.stripe.com'));
    }
}
