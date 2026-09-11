<?php

namespace Abdulsalam\LaravelContextFlow\Support;

use Abdulsalam\LaravelContextFlow\Contracts\IdGenerator;

final class UuidV7Generator implements IdGenerator
{
    public function generate(): string
    {
        $milliseconds = (int) floor(microtime(true) * 1000);
        $bytes = random_bytes(16);

        for ($i = 5; $i >= 0; --$i) {
            $bytes[$i] = chr($milliseconds & 0xff);
            $milliseconds >>= 8;
        }

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
