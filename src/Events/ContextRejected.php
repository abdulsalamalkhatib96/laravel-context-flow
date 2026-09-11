<?php

namespace Abdulsalam\LaravelContextFlow\Events;

final readonly class ContextRejected
{
    public function __construct(
        public string $reason,
        public ?string $key = null,
    ) {
    }
}
