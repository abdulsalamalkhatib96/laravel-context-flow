<?php

namespace Abdulsalam\LaravelContextFlow\Security;

use InvalidArgumentException;

final readonly class SensitiveKeyDetector
{
    /** @param list<string> $patterns */
    public function __construct(private array $patterns)
    {
        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, '') === false) {
                throw new InvalidArgumentException("Invalid sensitive-key regular expression [{$pattern}].");
            }
        }
    }

    public function isSensitive(string $key): bool
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $key) === 1) {
                return true;
            }
        }

        return false;
    }
}
