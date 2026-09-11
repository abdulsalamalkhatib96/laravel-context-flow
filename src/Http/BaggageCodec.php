<?php

namespace Abdulsalam\LaravelContextFlow\Http;

final class BaggageCodec
{
    /** @param array<string, string> $values */
    public function encode(array $values): string
    {
        ksort($values);

        return implode(',', array_map(
            static fn (string $key, string $value) => rawurlencode($key).'='.rawurlencode($value),
            array_keys($values),
            array_values($values),
        ));
    }

    /** @return array<string, string> */
    public function decode(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        $result = [];
        foreach (explode(',', $header) as $member) {
            $pair = explode('=', trim(explode(';', $member, 2)[0]), 2);
            if (count($pair) !== 2) {
                continue;
            }

            $key = rawurldecode(trim($pair[0]));
            $value = rawurldecode(trim($pair[1]));

            if ($key !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
