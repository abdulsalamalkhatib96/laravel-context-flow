<?php

namespace Abdulsalam\LaravelContextFlow\Contracts;

use Abdulsalam\LaravelContextFlow\Context\ContextKeyDefinition;
use Abdulsalam\LaravelContextFlow\Enums\PropagationTarget;
use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;

interface PropagationPolicy
{
    public function allows(ContextKeyDefinition $key, PropagationTarget $target, TrustLevel $trust): bool;
}
