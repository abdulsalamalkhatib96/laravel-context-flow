<?php

namespace Abdulsalam\LaravelContextFlow\Policy;

use Abdulsalam\LaravelContextFlow\Contracts\TrustResolver;
use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;

final readonly class HostTrustResolver implements TrustResolver
{
    /** @param list<string> $trustedHosts @param list<string> $partnerHosts */
    public function __construct(private array $trustedHosts = [], private array $partnerHosts = [])
    {
    }

    public function resolve(string $destination): TrustLevel
    {
        $host = strtolower((string) (parse_url($destination, PHP_URL_HOST) ?: $destination));

        foreach ($this->trustedHosts as $pattern) {
            if ($this->matches($host, strtolower($pattern))) {
                return TrustLevel::Internal;
            }
        }

        foreach ($this->partnerHosts as $pattern) {
            if ($this->matches($host, strtolower($pattern))) {
                return TrustLevel::Partner;
            }
        }

        return TrustLevel::Public;
    }

    private function matches(string $host, string $pattern): bool
    {
        if ($pattern === $host) {
            return true;
        }

        if (! str_contains($pattern, '*')) {
            return false;
        }

        $regex = '/^'.str_replace('\\*', '.*', preg_quote($pattern, '/')).'$/i';

        return (bool) preg_match($regex, $host);
    }
}
