<?php

namespace Abdulsalam\LaravelContextFlow\Tests\Unit;

use Abdulsalam\LaravelContextFlow\Security\SensitiveKeyDetector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SensitiveKeyDetectorTest extends TestCase
{
    public function test_it_detects_sensitive_key_names(): void
    {
        $detector = new SensitiveKeyDetector(['/token/i', '/password/i']);

        self::assertTrue($detector->isSensitive('api_token'));
        self::assertTrue($detector->isSensitive('Password'));
        self::assertFalse($detector->isSensitive('tenant_id'));
    }

    public function test_invalid_patterns_fail_closed_during_bootstrap(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SensitiveKeyDetector(['/broken[/']);
    }
}
