<?php

declare(strict_types=1);

namespace SymPress\Mailer\Support;

final class WordPressArray
{
    /**
     * @return array<string, mixed>
     */
    public static function post(): array
    {
        $post = $_POST;

        if (function_exists('wp_unslash')) {
            $post = wp_unslash($post);
        }

        return is_array($post) ? $post : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $get = $_GET;

        if (function_exists('wp_unslash')) {
            $get = wp_unslash($get);
        }

        return is_array($get) ? $get : [];
    }

    /**
     * @param mixed $value
     */
    public static function string($value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    public static function stringList($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            $string = self::string($item);

            if ($string !== '') {
                $strings[] = $string;
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @param mixed $value
     */
    public static function bool($value): bool
    {
        return in_array($value, [true, 1, '1', 'yes', 'on', 'true'], true);
    }

    /**
     * @param mixed $value
     */
    public static function int($value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }
}
