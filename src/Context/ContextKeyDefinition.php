<?php

namespace Abdulsalam\LaravelContextFlow\Context;

use Abdulsalam\LaravelContextFlow\Enums\PropagationTarget;
use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;

final readonly class ContextKeyDefinition
{
    /**
     * @param list<PropagationTarget> $targets
     * @param list<TrustLevel> $acceptFrom
     */
    public function __construct(
        public string $name,
        public array $targets,
        public array $acceptFrom,
        public int $priority = 100,
        public int $maxBytes = 1024,
    ) {
    }

    public function targets(PropagationTarget $target): bool
    {
        return in_array($target, $this->targets, true);
    }

    public function accepts(TrustLevel $trust): bool
    {
        return in_array($trust, $this->acceptFrom, true);
    }
}
