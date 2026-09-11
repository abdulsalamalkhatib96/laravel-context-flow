<?php

namespace Abdulsalam\LaravelContextFlow\Events;

use Abdulsalam\LaravelContextFlow\Context\ContextSnapshot;
use Abdulsalam\LaravelContextFlow\Enums\PropagationTarget;

final readonly class ContextPropagated
{
    /** @param list<string> $headerNames */
    public function __construct(
        public ContextSnapshot $context,
        public PropagationTarget $target,
        public string $destination,
        public array $headerNames,
    ) {
    }
}
