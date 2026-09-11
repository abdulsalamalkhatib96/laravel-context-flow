<?php

namespace Abdulsalam\LaravelContextFlow\Policy;

use Abdulsalam\LaravelContextFlow\Context\ContextKeyDefinition;
use Abdulsalam\LaravelContextFlow\Contracts\PropagationPolicy;
use Abdulsalam\LaravelContextFlow\Enums\PropagationTarget;
use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;

final class DefaultPropagationPolicy implements PropagationPolicy
{
    public function allows(ContextKeyDefinition $key, PropagationTarget $target, TrustLevel $trust): bool
    {
        if (! $key->targets($target)) {
            return false;
        }

        return match ($target) {
            PropagationTarget::InternalHttp => $trust === TrustLevel::Internal,
            PropagationTarget::PartnerHttp => $trust === TrustLevel::Partner,
            PropagationTarget::ExternalHttp => $trust === TrustLevel::Public,
            default => true,
        };
    }
}
