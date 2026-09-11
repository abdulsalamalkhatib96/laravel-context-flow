<?php

namespace Abdulsalam\LaravelContextFlow\Events;

use Abdulsalam\LaravelContextFlow\Context\ContextSnapshot;

final readonly class ContextBoundaryStarted
{
    public function __construct(public ContextSnapshot $context)
    {
    }
}
