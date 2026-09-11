<?php

namespace Abdulsalam\LaravelContextFlow\Tests\Unit;

use Abdulsalam\LaravelContextFlow\Support\UuidV7Generator;
use PHPUnit\Framework\TestCase;

final class UuidV7GeneratorTest extends TestCase
{
    public function test_it_generates_valid_uuid_v7_shape(): void
    {
        $id = (new UuidV7Generator())->generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id,
        );
    }

    public function test_ids_are_unique(): void
    {
        $generator = new UuidV7Generator();
        $ids = array_map(fn () => $generator->generate(), range(1, 100));

        self::assertCount(100, array_unique($ids));
    }
}
