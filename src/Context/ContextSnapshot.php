<?php

namespace Abdulsalam\LaravelContextFlow\Context;

use Abdulsalam\LaravelContextFlow\Enums\BoundaryType;
use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;

final readonly class ContextSnapshot
{
    /** @param array<string, mixed> $values */
    public function __construct(
        public array $values,
        public ?string $correlationId,
        public ?string $executionId,
        public ?string $causationId,
        public BoundaryType $boundary,
        public TrustLevel $trust,
    ) {
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}
