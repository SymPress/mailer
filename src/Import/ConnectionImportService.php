<?php

declare(strict_types=1);

namespace SymPress\Mailer\Import;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Provider\DefaultProviderRegistry;
use SymPress\Mailer\Provider\ProviderRegistryInterface;

final class ConnectionImportService
{
    public function __construct(
        private ?ProviderRegistryInterface $providers = null,
    ) {
    }

    /** @return list<ConnectionImportCandidate> */
    public function candidates(): array
    {
        if (!function_exists('get_option')) {
            return [];
        }

        $candidates = [];
        $wpMailSmtp = get_option('wp_mail_smtp');
        $easyWpSmtp = get_option('swpsmtp_options');
        $postSmtp = get_option('postman_options');
        $fluentSmtp = function_exists('fluentMailGetSettings')
            ? fluentMailGetSettings([], false)
            : get_option('fluentmail-settings');

        if (is_array($wpMailSmtp)) {
            $candidate = $this->fromWpMailSmtp($wpMailSmtp);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        if (is_array($easyWpSmtp)) {
            $candidate = $this->fromEasyWpSmtp($easyWpSmtp);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        if (is_array($postSmtp)) {
            $candidate = $this->fromPostSmtp($postSmtp);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        if (is_array($fluentSmtp)) {
            $candidate = $this->fromFluentSmtp($fluentSmtp);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    public function find(string $source): ?ConnectionImportCandidate
    {
        foreach ($this->candidates() as $candidate) {
            if ($candidate->source === $source) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $settings */
    public function fromWpMailSmtp(array $settings): ?ConnectionImportCandidate
    {
        $mail = $this->array($settings, 'mail');
        $provider = $this->string($mail, 'mailer');
        $common = [
            'id'              => 'primary',
            'name'            => 'Imported from WP Mail SMTP',
            'from_email'      => $this->string($mail, 'from_email'),
            'from_name'       => $this->string($mail, 'from_name'),
            'force_from'      => $this->bool($mail, 'from_email_force'),
            'force_from_name' => $this->bool($mail, 'from_name_force'),
            'return_path'     => $this->bool($mail, 'return_path'),
        ];

        $connection = match ($provider) {
            'smtp' => [
                ...$common,
                'provider'   => 'smtp',
                'host'       => $this->string($settings, 'smtp.host'),
                'port'       => $this->string($settings, 'smtp.port') ?: 587,
                'username'   => $this->string($settings, 'smtp.user'),
                'password'   => $this->string($settings, 'smtp.pass'),
                'encryption' => $this->string($settings, 'smtp.encryption') ?: 'tls',
                'auto_tls'   => $this->bool($settings, 'smtp.auto_tls', true),
            ],
            'mailgun' => [
                ...$common,
                'provider' => 'mailgun',
                'api_key'  => $this->string($settings, 'mailgun.api_key'),
                'domain'   => $this->string($settings, 'mailgun.domain'),
                'region'   => strtolower($this->string($settings, 'mailgun.region')),
            ],
            'sendgrid', 'sendinblue', 'smtp2go' => [
                ...$common,
                'provider' => $provider === 'sendinblue' ? 'brevo' : $provider,
                'api_key'  => $this->string($settings, $provider . '.api_key'),
            ],
            'pepipostapi' => [
                ...$common,
                'provider' => 'pepipost',
                'api_key'  => $this->string($settings, 'pepipostapi.api_key'),
            ],
            'amazonses' => [
                ...$common,
                'provider'   => 'ses',
                'api_key'    => $this->string($settings, 'amazonses.client_id'),
                'api_secret' => $this->string($settings, 'amazonses.client_secret'),
                'region'     => $this->string($settings, 'amazonses.region'),
            ],
            'mail' => [...$common, 'provider' => 'native'],
            default => [],
        };

        if ($connection === []) {
            return null;
        }

        return new ConnectionImportCandidate(
            'wp-mail-smtp',
            'WP Mail SMTP',
            'Import the detected WP Mail SMTP connection.',
            ConnectionConfig::fromArray($this->withProviderDefaults($connection), 'primary'),
        );
    }

    /** @param array<string, mixed> $settings */
    public function fromEasyWpSmtp(array $settings): ?ConnectionImportCandidate
    {
        $host = $this->string($settings, 'smtp_settings.host');

        if ($host === '') {
            return null;
        }

        return new ConnectionImportCandidate(
            'easy-wp-smtp',
            'Easy WP SMTP',
            'Import the detected Easy WP SMTP connection.',
            ConnectionConfig::fromArray(
                $this->withProviderDefaults(
                    [
                        'id'              => 'primary',
                        'name'            => 'Imported from Easy WP SMTP',
                        'provider'        => 'smtp',
                        'from_email'      => $this->string($settings, 'from_email_field'),
                        'from_name'       => $this->string($settings, 'from_name_field'),
                        'force_from'      => true,
                        'force_from_name' => $this->bool($settings, 'force_from_name_replace'),
                        'return_path'     => true,
                        'host'            => $host,
                        'port'            => $this->string($settings, 'smtp_settings.port') ?: 587,
                        'username'        => $this->string($settings, 'smtp_settings.username'),
                        'password'        => $this->string($settings, 'smtp_settings.password'),
                        'encryption'      => $this->string($settings, 'smtp_settings.type_encryption') ?: 'tls',
                    ],
                ),
                'primary',
            ),
        );
    }

    /** @param array<string, mixed> $settings */
    public function fromPostSmtp(array $settings): ?ConnectionImportCandidate
    {
        $host = $this->string($settings, 'hostname');

        if ($host === '') {
            return null;
        }

        return new ConnectionImportCandidate(
            'post-smtp',
            'Post SMTP',
            'Import the detected Post SMTP connection.',
            ConnectionConfig::fromArray(
                $this->withProviderDefaults(
                    [
                        'id'         => 'primary',
                        'name'       => 'Imported from Post SMTP',
                        'provider'   => 'smtp',
                        'from_email' => $this->string($settings, 'sender_email') ?: $this->string($settings, 'envelope_sender'),
                        'from_name'  => $this->string($settings, 'sender_name'),
                        'host'       => $host,
                        'port'       => $this->string($settings, 'port') ?: 587,
                        'username'   => $this->string($settings, 'username'),
                        'password'   => $this->string($settings, 'password'),
                        'encryption' => $this->string($settings, 'security_type') ?: 'tls',
                    ],
                ),
                'primary',
            ),
        );
    }

    /** @param array<string, mixed> $settings */
    public function fromFluentSmtp(array $settings): ?ConnectionImportCandidate
    {
        if (!function_exists('fluentMailGetSettings') && !empty($settings['use_encrypt'])) {
            return null;
        }

        $providerSettings = $this->fluentProviderSettings($settings);

        if ($providerSettings === []) {
            return null;
        }

        $connection = $this->fluentConnection($providerSettings);

        if ($connection === []) {
            return null;
        }

        return new ConnectionImportCandidate(
            'fluent-smtp',
            'Fluent SMTP',
            'Import the detected Fluent SMTP default connection.',
            ConnectionConfig::fromArray($this->withProviderDefaults($connection), 'primary'),
        );
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function fluentProviderSettings(array $settings): array
    {
        $connections = $this->array($settings, 'connections');

        if ($connections === []) {
            return [];
        }

        $default = $this->string($settings, 'misc.default_connection');

        if ($default !== '' && is_array($connections[$default] ?? null)) {
            return $this->array($connections[$default], 'provider_settings');
        }

        $first = reset($connections);

        return is_array($first) ? $this->array($first, 'provider_settings') : [];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function fluentConnection(array $settings): array
    {
        $provider = $this->string($settings, 'provider');
        $common = [
            'id'              => 'primary',
            'name'            => 'Imported from Fluent SMTP',
            'from_email'      => $this->string($settings, 'sender_email'),
            'from_name'       => $this->string($settings, 'sender_name'),
            'force_from'      => $this->bool($settings, 'force_from_email', true),
            'force_from_name' => $this->bool($settings, 'force_from_name'),
            'return_path'     => $this->bool($settings, 'return_path'),
            'key_store'       => $this->fluentKeyStore($settings),
            'secret_prefix'   => $this->fluentSecretPrefix($provider),
        ];

        return match ($provider) {
            'smtp' => [
                ...$common,
                'provider'   => 'smtp',
                'host'       => $this->string($settings, 'host'),
                'port'       => $this->string($settings, 'port') ?: 587,
                'username'   => $this->string($settings, 'username'),
                'password'   => $this->string($settings, 'password'),
                'encryption' => $this->string($settings, 'encryption') ?: 'none',
                'auto_tls'   => $this->bool($settings, 'auto_tls', true),
            ],
            'default' => [...$common, 'provider' => 'native'],
            'tosend', 'sendgrid', 'smtp2go', 'pepipost', 'postmark' => [
                ...$common,
                'provider' => $provider,
                'api_key'  => $this->string($settings, 'api_key'),
            ],
            'sendinblue' => [
                ...$common,
                'provider' => 'brevo',
                'api_key'  => $this->string($settings, 'api_key'),
            ],
            'ses' => [
                ...$common,
                'provider'   => 'ses',
                'api_key'    => $this->string($settings, 'access_key'),
                'api_secret' => $this->string($settings, 'secret_key'),
                'region'     => $this->string($settings, 'region'),
            ],
            'mailgun' => [
                ...$common,
                'provider' => 'mailgun',
                'api_key'  => $this->string($settings, 'api_key'),
                'domain'   => $this->string($settings, 'domain_name'),
                'region'   => $this->string($settings, 'region'),
            ],
            'cloudflare' => [
                ...$common,
                'provider'  => 'cloudflare',
                'api_key'   => $this->string($settings, 'api_key'),
                'tenant_id' => $this->string($settings, 'account_id'),
            ],
            'transmail' => [
                ...$common,
                'provider' => 'transmail',
                'api_key'  => $this->string($settings, 'api_key'),
                'domain'   => $this->string($settings, 'domain_name'),
            ],
            default => [],
        };
    }

    /** @param array<string, mixed> $settings */
    private function fluentKeyStore(array $settings): string
    {
        return $this->string($settings, 'key_store') === 'wp_config' ? 'wp_config' : 'option';
    }

    private function fluentSecretPrefix(string $provider): string
    {
        return match ($provider) {
            'smtp' => 'FLUENTMAIL_SMTP',
            'sendgrid' => 'FLUENTMAIL_SENDGRID',
            'sendinblue' => 'FLUENTMAIL_SENDINBLUE',
            'smtp2go' => 'FLUENTMAIL_SMTP2GO',
            'pepipost' => 'FLUENTMAIL_PEPIPOST',
            'postmark' => 'FLUENTMAIL_POSTMARK',
            'tosend' => 'FLUENTMAIL_TOSEND',
            'mailgun' => 'FLUENTMAIL_MAILGUN',
            'cloudflare' => 'FLUENTMAIL_CLOUDFLARE',
            'transmail' => 'FLUENTMAIL_TRANSMAIL',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function array(array $data, string $key): array
    {
        $value = $this->value($data, $key);

        return is_array($value) ? $value : [];
    }

    /** @param array<string, mixed> $data */
    private function string(array $data, string $key): string
    {
        $value = $this->value($data, $key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<string, mixed> $data */
    private function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $this->value($data, $key);

        if ($value === null) {
            return $default;
        }

        return in_array($value, [true, 1, '1', 'yes', 'on', 'true'], true);
    }

    /** @param array<string, mixed> $data */
    private function value(array $data, string $key): mixed
    {
        $value = $data;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $connection
     * @return array<string, mixed>
     */
    private function withProviderDefaults(array $connection): array
    {
        $providers = $this->providers ??= new DefaultProviderRegistry();
        $provider = $providers->get($this->string($connection, 'provider'));

        return $provider?->applyDefaults($connection) ?? $connection;
    }
}
