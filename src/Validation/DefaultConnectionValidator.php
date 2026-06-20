<?php

declare(strict_types=1);

namespace SymPress\Mailer\Validation;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Provider\ProviderRegistryInterface;
use SymPress\Mailer\Secret\ConnectionSecretResolverInterface;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;

final readonly class DefaultConnectionValidator implements ConnectionValidatorInterface
{
    private const array KEY_STORES = ['option', 'encrypted_option', 'env', 'config', 'wp_config'];

    public function __construct(
        private ProviderRegistryInterface $providers,
        private ConnectionSecretResolverInterface $secrets,
    ) {
    }

    #[\Override]
    public function validate(ConnectionConfig $connection): ConnectionValidationResult
    {
        $connection = $this->secrets->resolve($connection);
        $definition = $this->providers->get($connection->provider);
        $errors = [];

        if (!in_array($connection->keyStore, self::KEY_STORES, true)) {
            $errors['key_store'][] = 'Must be one of: ' . implode(', ', self::KEY_STORES) . '.';
        }

        if ($definition === null) {
            $errors['provider'][] = 'Unknown mail provider.';

            return new ConnectionValidationResult($errors);
        }

        if ($connection->provider === 'dsn' || $connection->dsn === '') {
            foreach ($definition->requiredFields as $field) {
                if ($this->value($connection, $field) !== '') {
                    continue;
                }

                $errors[$field][] = $this->requiredMessage($connection, $field, $definition->title);
            }

            foreach ($definition->options as $field => $options) {
                $value = $this->value($connection, (string) $field);

                if ($value === '' || array_key_exists($value, $options)) {
                    continue;
                }

                $errors[(string) $field][] = 'Must be one of: ' . implode(', ', array_keys($options)) . '.';
            }
        }

        if ($connection->fromEmail !== '' && filter_var($connection->fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['from_email'][] = 'Must be a valid email address.';
        }

        if ($connection->port < 1 || $connection->port > 65535) {
            $errors['port'][] = 'Must be a valid TCP port.';
        }

        if ($definition->supports('custom_transport_required') && !$definition->supports('custom_transport') && $connection->dsn === '' && $connection->host === '') {
            $errors['provider'][] = $definition->title . ' needs a custom Symfony transport or an SMTP-compatible host before it can send.';
        }

        return new ConnectionValidationResult($errors);
    }

    private function value(ConnectionConfig $connection, string $field): string
    {
        return match ($field) {
            'dsn' => $connection->dsn,
            'host' => $connection->host,
            'username' => $connection->username,
            'password' => $connection->password,
            'api_key' => $connection->apiKey,
            'api_secret' => $connection->apiSecret,
            'domain' => $connection->domain,
            'region' => $connection->region,
            'tenant_id' => $connection->tenantId,
            'from_email' => $connection->fromEmail,
            default => '',
        };
    }

    private function requiredMessage(ConnectionConfig $connection, string $field, string $providerTitle): string
    {
        $message = 'Required for ' . $providerTitle . '.';

        if (!$this->secrets instanceof EnvironmentConnectionSecretResolver) {
            return $message;
        }

        $names = $this->secrets->candidateNames($connection, $field);

        if ($names === []) {
            return $message;
        }

        return $message . ' Set one of: ' . implode(', ', $names) . '.';
    }
}
