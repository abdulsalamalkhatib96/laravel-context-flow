<?php

namespace Abdulsalam\LaravelContextFlow\Security;

final readonly class ContextSigner
{
    public function __construct(private ?string $key)
    {
    }

    public function available(): bool
    {
        return is_string($this->key) && strlen($this->key) >= 32;
    }

    public function sign(string $correlationId, string $causationId, string $baggage, int $timestamp): ?string
    {
        if (! $this->available()) {
            return null;
        }

        return 'sha256='.hash_hmac('sha256', $this->canonical($correlationId, $causationId, $baggage, $timestamp), $this->key);
    }

    public function verify(string $signature, string $correlationId, string $causationId, string $baggage, int $timestamp): bool
    {
        $expected = $this->sign($correlationId, $causationId, $baggage, $timestamp);

        return $expected !== null && hash_equals($expected, $signature);
    }

    private function canonical(string $correlationId, string $causationId, string $baggage, int $timestamp): string
    {
        return implode("\n", [$correlationId, $causationId, $baggage, (string) $timestamp]);
    }
}
