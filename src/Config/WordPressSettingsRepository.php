<?php

declare(strict_types=1);

namespace SymPress\Mailer\Config;

use SymPress\Mailer\Secret\SecretCipher;
use SymPress\Mailer\Secret\SecretFields;

final readonly class WordPressSettingsRepository implements SettingsRepositoryInterface
{
    public function __construct(
        private string $optionName,
    ) {
    }

    public function get(): MailerSettings
    {
        $data = [];

        $network = $this->usesNetworkOptions(true);
        if ($network) {
            $option = get_site_option($this->optionName, []);
            $data = is_array($option) ? $option : [];
        } elseif (function_exists('get_option')) {
            $option = get_option($this->optionName, []);
            $data = is_array($option) ? $option : [];
        }

        $plain = $this->mapSecrets($data, false);
        if ($this->needsMigration($data)) {
            $this->write($this->mapSecrets($plain, true), $data, $network);
        }
        $data = $plain;
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('sympress_mailer_settings', $data);
            $data = is_array($filtered) ? $filtered : $data;
        }

        return MailerSettings::fromArray($data);
    }

    public function save(MailerSettings $settings): void
    {
        $this->write($this->mapSecrets($settings->toArray(), true));
    }

    /** Call in maintenance mode before replacing the server key; drain queues first. */
    public function rotateSecrets(SecretCipher $previous, SecretCipher $replacement): void
    {
        $raw = $this->usesNetworkOptions() ? get_site_option($this->optionName, []) : get_option($this->optionName, []);
        if (!is_array($raw)) {
            throw new \RuntimeException('Invalid mailer settings storage.');
        }
        // Complete authentication/decryption before changing the stored option.
        $plain = $this->mapSecrets($raw, false, $previous);
        $this->write($this->mapSecrets($plain, true, $replacement));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $previous
     */
    private function write(array $data, ?array $previous = null, ?bool $network = null): void
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;
        $network ??= $this->usesNetworkOptions();
        if ($previous !== null && $wpdb instanceof \wpdb) {
            // A migration must never overwrite an administrator's concurrent update.
            $changed = $network
                ? $wpdb->query((string) $wpdb->prepare('UPDATE %i SET meta_value = %s WHERE site_id = %d AND meta_key = %s AND meta_value = %s', $wpdb->sitemeta, maybe_serialize($data), get_current_network_id(), $this->optionName, maybe_serialize($previous)))
                : $wpdb->query((string) $wpdb->prepare('UPDATE %i SET option_value = %s WHERE option_name = %s AND option_value = %s', $wpdb->options, maybe_serialize($data), $this->optionName, maybe_serialize($previous)));
            if ($changed !== 1) {
                throw new \RuntimeException('Mailer settings migration could not be persisted.');
            }
            if ($network) {
                wp_cache_delete(get_current_network_id() . ':' . $this->optionName, 'site-options');
            } else {
                wp_cache_delete($this->optionName, 'options');
                wp_cache_delete('alloptions', 'options');
            }
        } elseif ($network) {
            update_site_option($this->optionName, $data);
        } elseif (function_exists('update_option')) {
            update_option($this->optionName, $data, false);
        } else {
            throw new \RuntimeException('Mailer settings storage is unavailable.');
        }
        $stored = $network ? get_site_option($this->optionName, []) : get_option($this->optionName, []);
        if ($stored !== $data) {
            throw new \RuntimeException('Mailer settings could not be persisted.');
        }
    }

    private function usesNetworkOptions(bool $read = false): bool
    {
        if (SettingsScope::network()) {
            return true;
        }

        // Runtime delivery inherits network defaults only when the site has no override.
        // Admin editing and all writes retain their explicit, capability-checked scope.
        return $read && function_exists('is_multisite') && is_multisite()
            && (!function_exists('is_admin') || !is_admin())
            && get_option($this->optionName, null) === null
            && is_array(get_site_option($this->optionName, null));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function mapSecrets(array $data, bool $encrypt, ?SecretCipher $cipher = null): array
    {
        $cipher ??= new SecretCipher();
        foreach (['connection', 'backup_connection'] as $field) {
            if (!is_array($data[$field] ?? null)) {
                continue;
            }

            $data[$field] = $this->mapConnection($data[$field], $encrypt, $cipher);
        }
        foreach (is_array($data['connections'] ?? null) ? $data['connections'] : [] as $id => $connection) {
            if (!is_array($connection)) {
                continue;
            }

            $data['connections'][$id] = $this->mapConnection($connection, $encrypt, $cipher);
        }
        foreach (SecretFields::ALERT as $field) {
            if (!is_string($data[$field] ?? null) || $data[$field] === '') {
                continue;
            }

            $data[$field] = $encrypt ? $cipher->encrypt($data[$field]) : $cipher->decrypt($data[$field]);
        }
        foreach (is_array($data['alert_webhooks'] ?? null) ? $data['alert_webhooks'] : [] as $id => $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }

            $data['alert_webhooks'][$id] = $encrypt ? $cipher->encrypt($value) : $cipher->decrypt($value);
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $connection
     * @return array<string, mixed>
     */
    private function mapConnection(array $connection, bool $encrypt, SecretCipher $cipher): array
    {
        // External secret sources store no option credentials, including abandoned old values.
        if (!in_array($connection['key_store'] ?? 'encrypted_option', ['option', 'encrypted_option'], true)) {
            foreach (SecretFields::CONNECTION as $field) {
                $connection[$field] = '';
            }
            return $connection;
        }
        $connection['key_store'] = 'encrypted_option';
        foreach (SecretFields::CONNECTION as $field) {
            if (!is_string($connection[$field] ?? null) || $connection[$field] === '') {
                continue;
            }

            $connection[$field] = $encrypt ? $cipher->encrypt($connection[$field]) : $cipher->decrypt($connection[$field]);
        }
        return $connection;
    }

    /** @param array<string, mixed> $data */
    private function needsMigration(array $data): bool
    {
        foreach (['connection', 'backup_connection', ...array_keys(is_array($data['connections'] ?? null) ? $data['connections'] : [])] as $field) {
            $connection = $data[$field] ?? $data['connections'][$field] ?? null;
            if (!is_array($connection)) {
                continue;
            }
            if (!in_array($connection['key_store'] ?? 'option', ['option', 'encrypted_option'], true)) {
                foreach (SecretFields::CONNECTION as $secret) {
                    if (!empty($connection[$secret])) {
                        return true; // Remove abandoned option secrets when using external configuration.
                    }
                }
                continue;
            }
            foreach (SecretFields::CONNECTION as $secret) {
                if (is_string($connection[$secret] ?? null) && $connection[$secret] !== '' && !str_starts_with($connection[$secret], 'enc:v2:')) {
                    return true;
                }
            }
        }
        foreach ([...SecretFields::ALERT, 'alert_webhooks'] as $field) {
            foreach ((array) ($data[$field] ?? []) as $value) {
                if (is_string($value) && $value !== '' && !str_starts_with($value, 'enc:v2:')) {
                    return true;
                }
            }
        }
        return false;
    }
}
