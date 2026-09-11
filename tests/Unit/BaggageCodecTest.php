<?php

namespace Abdulsalam\LaravelContextFlow\Tests\Unit;

use Abdulsalam\LaravelContextFlow\Http\BaggageCodec;
use PHPUnit\Framework\TestCase;

final class BaggageCodecTest extends TestCase
{
    public function test_it_round_trips_values(): void
    {
        $codec = new BaggageCodec();
        $encoded = $codec->encode(['tenant_id' => '42', 'locale' => 'ar AE']);

        self::assertSame(['locale' => 'ar AE', 'tenant_id' => '42'], $codec->decode($encoded));
    }

    public function test_it_ignores_malformed_members(): void
    {
        $codec = new BaggageCodec();
        self::assertSame(['a' => '1'], $codec->decode('bad,a=1,also-bad'));
    }
}
