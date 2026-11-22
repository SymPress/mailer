<?php

declare(strict_types=1);

namespace SymPress\Mailer\Admin;

use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Config\SettingsScope;
use SymPress\Mailer\Secret\SecretFields;
use SymPress\Mailer\Support\WordPressArray;

/** Stateless rendering, nonce, redirect and Pro guard helpers shared by the admin screens. */
trait AdminScreenHelpers
{
    private const string SLUG = 'sympress-mailer';
    private const string PRO_ENTRY = 'mailer-pro/mailer-pro.php';

    private function chromeStart(string $title, MailerSettings $settings): void
    {
        echo '<div class="wrap sympress-mailer">';
        echo '<section class="spm-section spm-hero">';
        echo '<div><h1>' . esc_html__('SymPress Mailer', 'sympress-mailer') . '</h1><p>' . esc_html__('Symfony Mailer delivery for WordPress.', 'sympress-mailer') . '</p></div>';
        echo '<span class="spm-status ' . ($settings->enabled ? 'is-on' : 'is-off') . '">';
        echo $settings->enabled ? __('Enabled', 'sympress-mailer') : __('Disabled', 'sympress-mailer');
        echo '</span></section>';
        echo '<section class="spm-section spm-page-title"><h2>' . $this->esc($title) . '</h2></section>';
    }

    private function chromeEnd(): void
    {
        echo '</div>';
    }

    private function switchRow(string $name, string $label, bool $checked): void
    {
        echo '<label class="spm-switch-row"><span>' . $this->esc($label) . '</span><span class="spm-switch">';
        echo '<input type="checkbox" name="' . $this->attr($name) . '" value="1"' . ($checked ? ' checked' : '') . '>';
        echo '<span></span></span></label>';
    }

    private function input(string $name, string $label, string $value, string $type = 'text'): void
    {
        $secret = SecretFields::isSecret(SecretFields::fieldName($name));
        if ($secret) {
            $value = '';
            $type = 'password';
        }
        echo '<label class="spm-field"><span>' . $this->esc($label) . '</span>';
        echo '<input type="' . $this->attr($type) . '" name="' . $this->attr($name) . '" value="' . $this->attr($value) . '">';
        echo '</label>';
        if (!$secret) {
            return;
        }

        $this->switchRow(SecretFields::clearName($name), __('Clear stored secret', 'sympress-mailer'), false);
    }

    private function assertCapability(): void
    {
        SettingsScope::assertCapability();
    }

    private function checkNonce(string $action): void
    {
        if (!function_exists('check_admin_referer')) {
            return;
        }

        check_admin_referer(isset(WordPressArray::post()['_sympress_scope']) ? SettingsScope::nonce($action) : $action);
    }

    private function nonce(string $action): void
    {
        if (!function_exists('wp_nonce_field')) {
            return;
        }

        echo '<input type="hidden" name="_sympress_scope" value="' . (SettingsScope::network() ? 'network' : 'site') . '">';
        wp_nonce_field(SettingsScope::nonce($action));
    }

    /** @param array<string, string> $args */
    private function redirect(string $page, array $args = []): never
    {
        $url = $this->adminUrl('admin.php', ['page' => $page, ...$args]);

        if (function_exists('wp_safe_redirect')) {
            wp_safe_redirect($url);
        } else {
            header('Location: ' . $url);
        }

        exit;
    }

    /** @param array<string, string> $args */
    private function adminUrl(string $path, array $args = []): string
    {
        $url = SettingsScope::network() && $path !== 'admin-post.php' && function_exists('network_admin_url') ? network_admin_url($path) : (function_exists('admin_url') ? admin_url($path) : '/wp-admin/' . ltrim($path, '/'));

        return $args === []
            ? $url
            : $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($args);
    }

    private function adminPostUrl(): string
    {
        return $this->adminUrl('admin-post.php');
    }

    private function proPluginActive(): bool
    {
        if (!function_exists('get_option')) {
            return false;
        }

        $active = get_option('active_plugins', []);

        if (is_array($active) && in_array(self::PRO_ENTRY, $active, true)) {
            return true;
        }

        if (!function_exists('is_multisite') || !is_multisite() || !function_exists('get_site_option')) {
            return false;
        }

        $network = get_site_option('active_sitewide_plugins', []);

        return is_array($network) && array_key_exists(self::PRO_ENTRY, $network);
    }

    private function esc(string $value): string
    {
        return function_exists('esc_html') ? esc_html($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function attr(string $value): string
    {
        return function_exists('esc_attr') ? esc_attr($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
