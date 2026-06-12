<?php

declare(strict_types=1);

namespace SymPress\Mailer\Hook;

final class Uninstaller
{
    private const string OPTION_NAME = 'sympress_mailer_settings';

    public static function uninstall(): void
    {
        $settings = self::settings();

        if (!is_array($settings) || ($settings['uninstall_data'] ?? false) !== true) {
            return;
        }

        if (function_exists('delete_option')) {
            delete_option(self::OPTION_NAME);
        }

        if (!function_exists('delete_site_option')) {
            return;
        }

        delete_site_option(self::OPTION_NAME);
    }

    /** @return array<string, mixed>|null */
    private static function settings(): ?array
    {
        $settings = null;

        if (function_exists('get_option')) {
            $option = get_option(self::OPTION_NAME, null);
            $settings = is_array($option) ? $option : $settings;
        }

        if ($settings === null && function_exists('get_site_option')) {
            $option = get_site_option(self::OPTION_NAME, null);
            $settings = is_array($option) ? $option : null;
        }

        return $settings;
    }
}
