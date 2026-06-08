<?php

declare(strict_types=1);

namespace SymPress\Mailer\Config;

final readonly class WordPressSettingsRepository implements SettingsRepositoryInterface
{
    public function __construct(
        private string $optionName,
    ) {
    }

    public function get(): MailerSettings
    {
        $data = [];

        if ($this->usesNetworkOptions()) {
            $option = get_site_option($this->optionName, []);
            $data = is_array($option) ? $option : [];
        } elseif (function_exists('get_option')) {
            $option = get_option($this->optionName, []);
            $data = is_array($option) ? $option : [];
        }

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('sympress_mailer_settings', $data);
            $data = is_array($filtered) ? $filtered : $data;
        }

        return MailerSettings::fromArray($data);
    }

    public function save(MailerSettings $settings): void
    {
        if ($this->usesNetworkOptions()) {
            update_site_option($this->optionName, $settings->toArray());
            return;
        }

        if (!function_exists('update_option')) {
            return;
        }

        update_option($this->optionName, $settings->toArray(), false);
    }

    private function usesNetworkOptions(): bool
    {
        return function_exists('is_multisite')
            && is_multisite()
            && function_exists('is_network_admin')
            && is_network_admin()
            && function_exists('get_site_option')
            && function_exists('update_site_option');
    }
}
