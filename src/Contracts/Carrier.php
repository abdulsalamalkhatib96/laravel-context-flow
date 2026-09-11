<?php

namespace Abdulsalam\LaravelContextFlow\Contracts;

interface Carrier
{
    public function get(string $key): ?string;

    public function set(string $key, string $value): void;

    /** @return array<string, string> */
    public function all(): array;
}
