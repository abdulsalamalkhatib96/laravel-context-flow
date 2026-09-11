<?php

namespace Abdulsalam\LaravelContextFlow\Context;

use Abdulsalam\LaravelContextFlow\Enums\PropagationTarget;
use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;
use InvalidArgumentException;

final class ContextRegistry
{
    /** @var array<string, ContextKeyDefinition> */
    private array $definitions = [];

    /** @param array<string, array<string, mixed>> $config */
    public function __construct(array $config = [])
    {
        foreach ($config as $name => $definition) {
            $this->definitions[$name] = $this->make($name, $definition);
        }
    }

    public function get(string $name): ?ContextKeyDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    /** @return array<string, ContextKeyDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->definitions);
    }

    private function make(string $name, array $config): ContextKeyDefinition
    {
        $targets = array_map(
            static fn (string $value) => PropagationTarget::tryFrom($value)
                ?? throw new InvalidArgumentException("Invalid propagation target [{$value}] for context key [{$name}]."),
            array_values($config['targets'] ?? []),
        );

        $acceptFrom = array_map(
            static fn (string $value) => TrustLevel::tryFrom($value)
                ?? throw new InvalidArgumentException("Invalid trust level [{$value}] for context key [{$name}]."),
            array_values($config['accept_from'] ?? []),
        );

        return new ContextKeyDefinition(
            name: $name,
            targets: $targets,
            acceptFrom: $acceptFrom,
            priority: max(0, (int) ($config['priority'] ?? 100)),
            maxBytes: max(1, (int) ($config['max_bytes'] ?? 1024)),
        );
    }
}
