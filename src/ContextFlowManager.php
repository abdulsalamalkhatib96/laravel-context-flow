<?php

namespace Abdulsalam\LaravelContextFlow;

use Abdulsalam\LaravelContextFlow\Context\ContextRegistry;
use Abdulsalam\LaravelContextFlow\Context\ContextSnapshot;
use Abdulsalam\LaravelContextFlow\Contracts\IdGenerator;
use Abdulsalam\LaravelContextFlow\Contracts\PropagationPolicy;
use Abdulsalam\LaravelContextFlow\Enums\BoundaryType;
use Abdulsalam\LaravelContextFlow\Enums\PropagationTarget;
use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;
use Abdulsalam\LaravelContextFlow\Events\ContextBoundaryStarted;
use Abdulsalam\LaravelContextFlow\Events\ContextRejected;
use Abdulsalam\LaravelContextFlow\Exceptions\ContextTooLargeException;
use Abdulsalam\LaravelContextFlow\Security\SensitiveKeyDetector;
use Abdulsalam\LaravelContextFlow\Support\ContextKeys;
use Abdulsalam\LaravelContextFlow\Support\ValueNormalizer;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Context;

final class ContextFlowManager
{
    public function __construct(
        private readonly IdGenerator $ids,
        private readonly ContextRegistry $registry,
        private readonly PropagationPolicy $policy,
        private readonly SensitiveKeyDetector $sensitive,
        private readonly ValueNormalizer $normalizer,
        private readonly Dispatcher $events,
        private readonly array $config,
    ) {
    }

    public function startRoot(BoundaryType $boundary, ?string $correlationId = null, ?string $causationId = null, TrustLevel $trust = TrustLevel::Local): void
    {
        $this->resetManagedContext();

        Context::add([
            ContextKeys::CORRELATION_ID => $correlationId ?: $this->ids->generate(),
            ContextKeys::EXECUTION_ID => $this->ids->generate(),
            ContextKeys::BOUNDARY => $boundary->value,
            ContextKeys::ORIGIN => $boundary->value,
            ContextKeys::TRUST => $trust->value,
            ContextKeys::SERVICE => (string) ($this->config['service'] ?? 'laravel'),
            ContextKeys::ENVIRONMENT => (string) ($this->config['environment'] ?? 'production'),
        ]);

        if ($causationId !== null && $causationId !== '') {
            Context::add(ContextKeys::CAUSATION_ID, $causationId);
        }

        if ($boundary === BoundaryType::Http) {
            Context::add(ContextKeys::REQUEST_ID, $this->ids->generate());
        }

        $this->events->dispatch(new ContextBoundaryStarted($this->snapshotRaw()));
    }

    public function ensureRoot(BoundaryType $boundary, TrustLevel $trust = TrustLevel::Local): void
    {
        if (! Context::has(ContextKeys::CORRELATION_ID) || ! Context::has(ContextKeys::EXECUTION_ID)) {
            $this->startRoot($boundary, trust: $trust);
        }
    }

    public function rotateExecution(BoundaryType $boundary): void
    {
        $oldExecution = $this->executionId();

        if ($this->correlationId() === null || $oldExecution === null) {
            $this->startRoot($boundary);
            return;
        }

        Context::add([
            ContextKeys::CAUSATION_ID => $oldExecution,
            ContextKeys::EXECUTION_ID => $this->ids->generate(),
            ContextKeys::BOUNDARY => $boundary->value,
            ContextKeys::TRUST => TrustLevel::Local->value,
        ]);

        Context::forget(ContextKeys::REQUEST_ID);
        $this->events->dispatch(new ContextBoundaryStarted($this->snapshotRaw()));
    }

    public function correlationId(): ?string
    {
        return $this->stringValue(ContextKeys::CORRELATION_ID);
    }

    public function executionId(): ?string
    {
        return $this->stringValue(ContextKeys::EXECUTION_ID);
    }

    public function causationId(): ?string
    {
        return $this->stringValue(ContextKeys::CAUSATION_ID);
    }

    public function requestId(): ?string
    {
        return $this->stringValue(ContextKeys::REQUEST_ID);
    }

    public function boundary(): BoundaryType
    {
        return BoundaryType::tryFrom((string) Context::get(ContextKeys::BOUNDARY)) ?? BoundaryType::Unknown;
    }

    public function trustLevel(): TrustLevel
    {
        return TrustLevel::tryFrom((string) Context::get(ContextKeys::TRUST)) ?? TrustLevel::Local;
    }

    public function snapshot(PropagationTarget $target, TrustLevel $trust): ContextSnapshot
    {
        if ((bool) Context::getHidden(ContextKeys::PROPAGATION_DISABLED, false)) {
            return new ContextSnapshot([], null, null, null, $this->boundary(), $trust);
        }

        $only = Context::getHidden(ContextKeys::ONLY_KEYS, null);
        $only = is_array($only) ? array_flip($only) : null;
        $values = [];

        // Transport identifiers are framework-level metadata and remain available
        // unless propagation has been explicitly disabled.
        foreach (ContextKeys::transportKeys() as $key) {
            if (Context::has($key)) {
                $values[$key] = Context::get($key);
            }
        }

        foreach ($this->registry->all() as $name => $definition) {
            if ($only !== null && ! isset($only[$name])) {
                continue;
            }

            if ((! Context::has($name) && ! Context::hasHidden($name)) || $this->sensitive->isSensitive($name)) {
                continue;
            }

            if (! $this->policy->allows($definition, $target, $trust)) {
                continue;
            }

            $value = Context::has($name) ? Context::get($name) : Context::getHidden($name);
            if (! $this->withinKeyLimits($name, $value, $definition->maxBytes)) {
                continue;
            }

            $values[$name] = $value;
        }

        $values = $this->enforceAggregateLimits($values);

        return new ContextSnapshot(
            values: $values,
            correlationId: isset($values[ContextKeys::CORRELATION_ID]) ? (string) $values[ContextKeys::CORRELATION_ID] : null,
            executionId: isset($values[ContextKeys::EXECUTION_ID]) ? (string) $values[ContextKeys::EXECUTION_ID] : null,
            causationId: isset($values[ContextKeys::CAUSATION_ID]) ? (string) $values[ContextKeys::CAUSATION_ID] : null,
            boundary: $this->boundary(),
            trust: $trust,
        );
    }

    public function snapshotRaw(): ContextSnapshot
    {
        return new ContextSnapshot(
            values: Context::all(),
            correlationId: $this->correlationId(),
            executionId: $this->executionId(),
            causationId: $this->causationId(),
            boundary: $this->boundary(),
            trust: $this->trustLevel(),
        );
    }

    public function resetManagedContext(bool $includeRegisteredKeys = true): void
    {
        $keys = ContextKeys::allManaged();
        if ($includeRegisteredKeys) {
            $keys = [...$keys, ...$this->registry->names()];
        }

        $keys = array_values(array_unique($keys));
        Context::forget($keys);
        Context::forgetHidden([...$keys, ContextKeys::PROPAGATION_DISABLED, ContextKeys::ONLY_KEYS]);
    }

    public function withoutPropagation(callable $callback): mixed
    {
        return Context::scope($callback, hidden: [ContextKeys::PROPAGATION_DISABLED => true]);
    }

    /** @param list<string> $keys */
    public function only(array $keys, callable $callback): mixed
    {
        return Context::scope($callback, hidden: [ContextKeys::ONLY_KEYS => array_values(array_unique($keys))]);
    }

    public function acceptsIncomingKey(string $key, TrustLevel $trust, string $value): bool
    {
        $definition = $this->registry->get($key);

        if ($definition === null || ! $definition->accepts($trust) || $this->sensitive->isSensitive($key)) {
            $this->events->dispatch(new ContextRejected('key_not_allowed', $key));
            return false;
        }

        if (! $this->isSafeIncomingString($value)) {
            $this->events->dispatch(new ContextRejected('unsafe_incoming_value', $key));
            return false;
        }

        if (! $this->withinKeyLimits($key, $value, $definition->maxBytes)) {
            return false;
        }

        return true;
    }

    public function isValidId(?string $value): bool
    {
        if ($value === null || $value === '' || strlen($value) > 128) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9._:-]+$/D', $value) === 1;
    }

    private function isSafeIncomingString(string $value): bool
    {
        if (preg_match('//u', $value) !== 1) {
            return false;
        }

        return preg_match('/[\x00-\x1F\x7F]/u', $value) !== 1;
    }

    private function withinKeyLimits(string $key, mixed $value, int $definitionMax): bool
    {
        $keyLimit = (int) ($this->config['limits']['key_bytes'] ?? 128);
        $valueLimit = min($definitionMax, (int) ($this->config['limits']['value_bytes'] ?? 1024));
        $normalized = $this->normalizer->toTransportString($value);

        if (strlen($key) > $keyLimit || $normalized === null || strlen($normalized) > $valueLimit) {
            $this->events->dispatch(new ContextRejected('key_or_value_too_large_or_unserializable', $key));
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function enforceAggregateLimits(array $values): array
    {
        $maxKeys = (int) ($this->config['limits']['max_keys'] ?? 32);
        $maxBytes = (int) ($this->config['limits']['http_total_bytes'] ?? 4096);
        $overflow = (string) ($this->config['limits']['overflow'] ?? 'drop_low_priority');

        // Use percent-encoded sizes so the HTTP limit remains conservative after
        // values are serialized into a baggage-style header.
        $bytes = fn (array $candidate): int => array_sum(array_map(
            function (string $key, mixed $value): int {
                $normalized = (string) ($this->normalizer->toTransportString($value) ?? '');

                return strlen(rawurlencode($key)) + 1 + strlen(rawurlencode($normalized)) + 1;
            },
            array_keys($candidate),
            array_values($candidate),
        ));

        if (count($values) <= $maxKeys && $bytes($values) <= $maxBytes) {
            return $values;
        }

        if ($overflow === 'throw') {
            throw new ContextTooLargeException('Context exceeds configured propagation limits.');
        }

        $protected = array_flip(ContextKeys::transportKeys());
        $candidates = array_keys(array_filter(
            $values,
            static fn (string $key) => ! isset($protected[$key]),
            ARRAY_FILTER_USE_KEY,
        ));

        usort($candidates, function (string $a, string $b): int {
            return ($this->registry->get($a)?->priority ?? 0) <=> ($this->registry->get($b)?->priority ?? 0);
        });

        foreach ($candidates as $key) {
            if (count($values) <= $maxKeys && $bytes($values) <= $maxBytes) {
                break;
            }
            unset($values[$key]);
        }

        if (count($values) > $maxKeys || $bytes($values) > $maxBytes) {
            // Transport metadata is useful, but configured hard limits still win.
            // Retain only the minimum lineage pair before giving up completely.
            $values = array_intersect_key($values, array_flip([
                ContextKeys::CORRELATION_ID,
                ContextKeys::EXECUTION_ID,
            ]));

            if (count($values) > $maxKeys || $bytes($values) > $maxBytes) {
                return [];
            }
        }

        return $values;
    }

    private function stringValue(string $key): ?string
    {
        $value = Context::get($key);
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
