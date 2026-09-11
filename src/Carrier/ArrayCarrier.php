<?php

namespace Abdulsalam\LaravelContextFlow\Carrier;

use Abdulsalam\LaravelContextFlow\Contracts\Carrier;

final class ArrayCarrier implements Carrier
{
    /** @param array<string, string> $values */
    public function __construct(private array $values = [])
    {
    }

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->values[$key] = $value;
    }

    public function all(): array
    {
        return $this->values;
    }
}
