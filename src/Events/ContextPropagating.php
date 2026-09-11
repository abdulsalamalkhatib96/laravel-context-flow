<?php

namespace Abdulsalam\LaravelContextFlow\Events;

use Abdulsalam\LaravelContextFlow\Context\ContextSnapshot;
use Abdulsalam\LaravelContextFlow\Enums\PropagationTarget;

final readonly class ContextPropagating
{
    public function __construct(
        public ContextSnapshot $context,
        public PropagationTarget $target,
        public string $destination,
    ) {
    }
}
