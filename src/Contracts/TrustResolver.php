<?php

namespace Abdulsalam\LaravelContextFlow\Contracts;

use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;

interface TrustResolver
{
    public function resolve(string $destination): TrustLevel;
}
