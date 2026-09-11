<?php

namespace Abdulsalam\LaravelContextFlow\Support;

use Stringable;
use Throwable;

final class ValueNormalizer
{
    public function toTransportString(mixed $value): ?string
    {
        try {
            if ($value === null) {
                return null;
            }

            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            if (is_scalar($value)) {
                return (string) $value;
            }

            if ($value instanceof Stringable) {
                return (string) $value;
            }

            if (is_array($value)) {
                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return $encoded === false ? null : $encoded;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
