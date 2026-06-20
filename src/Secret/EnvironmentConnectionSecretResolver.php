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
    ];

    #[\Override]
    public function resolve(ConnectionConfig $connection): ConnectionConfig
    {
        $values = $connection->toArray();

        foreach (self::FIELDS as $field) {
            $secret = $this->secret($connection, $field);

            if ($secret === null || $secret === '') {
                continue;
            }

            $values[$field] = $secret;
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
