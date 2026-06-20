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

        $data = $this->decryptSecrets($data);

        return MailerSettings::fromArray($data);
    }

    public function save(MailerSettings $settings): void
    {
        $data = $this->encryptSecrets($settings->toArray());

        if ($this->usesNetworkOptions()) {
            update_site_option($this->optionName, $data);
            return;
        }

        if (!function_exists('update_option')) {
            return;
        }

        update_option($this->optionName, $data, false);
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function encryptSecrets(array $data): array
    {
        return $this->mapConnections($data, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function decryptSecrets(array $data): array
    {
        return $this->mapConnections($data, false);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function mapConnections(array $data, bool $encrypt): array
    {
        if (is_array($data['connection'] ?? null)) {
            $data['connection'] = $this->mapConnection($data['connection'], $encrypt);
        }

        if (is_array($data['backup_connection'] ?? null)) {
            $data['backup_connection'] = $this->mapConnection($data['backup_connection'], $encrypt);
        }

        if (is_array($data['connections'] ?? null)) {
            foreach ($data['connections'] as $id => $connection) {
                if (!is_array($connection)) {
                    continue;
                }

                $data['connections'][$id] = $this->mapConnection($connection, $encrypt);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $connection
     * @return array<string, mixed>
     */
    private function mapConnection(array $connection, bool $encrypt): array
    {
        if (($connection['key_store'] ?? '') !== 'encrypted_option') {
            return $connection;
        }

        foreach (['dsn', 'username', 'password', 'api_key', 'api_secret', 'domain', 'tenant_id'] as $field) {
            if (!is_scalar($connection[$field] ?? null) || (string) $connection[$field] === '') {
                continue;
            }

            $connection[$field] = $encrypt
                ? $this->encrypt((string) $connection[$field])
                : $this->decrypt((string) $connection[$field]);
        }

        return $connection;
    }

    private function encrypt(string $value): string
    {
        if (str_starts_with($value, 'enc:v1:') || !function_exists('openssl_encrypt')) {
            return $value;
        }

        $key = $this->encryptionKey();

        if ($key === '') {
            return $value;
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if (!is_string($ciphertext)) {
            return $value;
        }

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary AES-GCM envelope parts are encoded for option storage.
        $encodedIv = base64_encode($iv);
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary AES-GCM envelope parts are encoded for option storage.
        $encodedTag = base64_encode($tag);
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary AES-GCM envelope parts are encoded for option storage.
        $encodedCiphertext = base64_encode($ciphertext);

        return 'enc:v1:' . $encodedIv . ':' . $encodedTag . ':' . $encodedCiphertext;
    }

    private function decrypt(string $value): string
    {
        if (!str_starts_with($value, 'enc:v1:') || !function_exists('openssl_decrypt')) {
            return $value;
        }

        $parts = explode(':', $value, 5);

        if (count($parts) !== 5) {
            return $value;
        }

        [, , $iv, $tag, $ciphertext] = $parts;
        $key = $this->encryptionKey();

        if ($key === '') {
            return $value;
        }

        $plain = openssl_decrypt(
            $this->base64Decode($ciphertext),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $this->base64Decode($iv),
            $this->base64Decode($tag),
        );

        return is_string($plain) ? $plain : $value;
    }

    private function base64Decode(string $value): string
    {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes binary AES-GCM envelope parts stored by encrypt().
        return (string) base64_decode($value, true);
    }

    private function encryptionKey(): string
    {
        $material = '';

        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $constant) {
            if (!defined($constant) || !is_scalar(constant($constant))) {
                continue;
            }

            $material .= (string) constant($constant);
        }

        return $material !== '' ? hash('sha256', $material, true) : '';
    }
}
