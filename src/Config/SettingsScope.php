<?php

declare(strict_types=1);

namespace SymPress\Mailer\Config;

use SymPress\Mailer\Support\WordPressArray;

/** Network admin-post explicitly carries scope; network context is absent there. */
final class SettingsScope
{
    public static function network(): bool
    {
        $network = function_exists('is_multisite') && is_multisite()
            && ((function_exists('is_network_admin') && is_network_admin()) || (function_exists('is_admin') && is_admin() && str_starts_with((string) (WordPressArray::post()['action'] ?? ''), 'sympress_mailer_') && (WordPressArray::post()['_sympress_scope'] ?? '') === 'network'));
        if ($network && (!function_exists('current_user_can') || !current_user_can('manage_network_options'))) {
            throw new \RuntimeException('Network mailer settings require manage_network_options.');
        }
        return $network;
    }

    public static function assertCapability(): void
    {
        $capability = self::network() ? 'manage_network_options' : 'manage_options';
        if (!function_exists('current_user_can') || !current_user_can($capability)) {
            throw new \RuntimeException('Insufficient permissions.');
        }
    }

    public static function nonce(string $action): string
    {
        return $action . (self::network() ? '_network' : '_site');
    }
}
