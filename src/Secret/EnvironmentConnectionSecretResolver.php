<?php

declare(strict_types=1);

namespace SymPress\Mailer\Secret;

use SymPress\Mailer\Config\ConnectionConfig;

final class EnvironmentConnectionSecretResolver implements ConnectionSecretResolverInterface
{
    private const array FIELDS = [
        'dsn',
        'host',
        'username',
        'password',
        'api_key',
        'api_secret',
        'domain',
        'region',
        'tenant_id',
        'port',
        'encryption',
        'auto_tls',
        'verify_peer',
    ];

    private const array CREDENTIAL_FIELDS = ['dsn', 'password', 'api_key', 'api_secret'];

    private const array DESTINATION_DEFAULTS = [
        'dsn'         => '',
        'host'        => '',
        'encryption'  => 'tls',
        'auto_tls'    => true,
        'verify_peer' => true,
    ];

    #[\Override]
    public function resolve(ConnectionConfig $connection): ConnectionConfig
    {
        if (!in_array($connection->keyStore, ['env', 'wp_config', 'config'], true)) {
            return $connection;
        }
        $external = [];

        foreach (self::FIELDS as $field) {
            $secret = $this->secret($connection, $field);

            if ($secret === null || $secret === '') {
                continue;
            }

            $external[$field] = $secret;
        }

        $values = [...$connection->toArray(), ...$external];

        if (array_intersect_key($external, array_flip(self::CREDENTIAL_FIELDS)) !== []) {
            // Form-supplied destinations could redirect an externally stored credential, so the
            // destination comes from the same source or falls back to the provider's built-in default.
            $values = [...$values, ...self::DESTINATION_DEFAULTS, ...$external];
            $values['port'] = $external['port'] ?? (in_array(strtolower((string) $values['encryption']), ['ssl', 'smtps'], true) ? 465 : 587);
        }

        return ConnectionConfig::fromArray($values, $connection->id);
    }

    private function secret(ConnectionConfig $connection, string $field): ?string
    {
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('sympress_mailer_connection_secret', null, $connection, $field);

            if (is_scalar($filtered) && (string) $filtered !== '') {
                return (string) $filtered;
            }
        }

        foreach ($this->candidateNames($connection, $field) as $name) {
            $value = $this->fromEnvironment($name);

            if ($value !== null && $value !== '') {
                return $value;
            }

            if (!defined($name)) {
                continue;
            }

            $constant = constant($name);

            if (is_scalar($constant) && (string) $constant !== '') {
                return (string) $constant;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function candidateNames(ConnectionConfig $connection, string $field): array
    {
        $field = strtoupper($field);
        $prefixes = array_filter(
            [
                $connection->secretPrefix,
                'SYMPRESS_MAILER_' . strtoupper(str_replace('-', '_', $connection->id)),
                'SYMPRESS_MAILER_' . strtoupper(str_replace('-', '_', $connection->provider)),
            ],
            static fn (string $prefix): bool => $prefix !== '',
        );

        $names = [];

        foreach ($prefixes as $prefix) {
            if (preg_match('/^SYMPRESS_MAILER_[A-Z0-9_]+$/D', strtoupper($prefix)) !== 1) {
                continue;
            }
            $names[] = strtoupper($prefix) . '_' . $field;
        }

        return array_values(array_unique($names));
    }

    private function fromEnvironment(string $name): ?string
    {
        $value = getenv($name);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        foreach ([$_ENV, $_SERVER] as $source) {
            if (!isset($source[$name]) || !is_scalar($source[$name])) {
                continue;
            }

            return (string) $source[$name];
        }

        return null;
    }
}
