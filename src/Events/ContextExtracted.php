<?php

namespace Abdulsalam\LaravelContextFlow\Events;

use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;

final readonly class ContextExtracted
{
    /** @param array<string, string> $values */
    public function __construct(public array $values, public TrustLevel $trust)
    {
    }
}
