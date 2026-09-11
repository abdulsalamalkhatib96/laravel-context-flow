<?php

namespace Abdulsalam\LaravelContextFlow\Tests\Unit;

use Abdulsalam\LaravelContextFlow\Security\ContextSigner;
use PHPUnit\Framework\TestCase;

final class ContextSignerTest extends TestCase
{
    public function test_it_rejects_weak_signing_keys(): void
    {
        $signer = new ContextSigner('too-short');

        self::assertFalse($signer->available());
        self::assertNull($signer->sign('c1', 'e1', 'tenant_id=1', 123));
    }

    public function test_it_signs_and_verifies_context(): void
    {
        $signer = new ContextSigner(str_repeat('k', 32));
        $signature = $signer->sign('c1', 'e1', 'tenant_id=1', 123);

        self::assertNotNull($signature);
        self::assertTrue($signer->verify($signature, 'c1', 'e1', 'tenant_id=1', 123));
        self::assertFalse($signer->verify($signature, 'c1', 'e2', 'tenant_id=1', 123));
    }
}
