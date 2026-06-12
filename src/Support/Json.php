<?php

declare(strict_types=1);

namespace SymPress\Mailer\Support;

final class Json
{
    /** @param mixed $value */
    public static function encode($value): string
    {
        if (function_exists('wp_json_encode')) {
            $encoded = wp_json_encode($value);

            return is_string($encoded) ? $encoded : 'null';
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    public static function object(string $json): array
    {
        if ($json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<mixed> */
    public static function list(string $json): array
    {
        $decoded = self::object($json);

        return array_values($decoded);
    }
}
