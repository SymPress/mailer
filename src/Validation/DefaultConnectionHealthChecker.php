<?php

declare(strict_types=1);

namespace SymPress\Mailer\Validation;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Provider\ProviderDefinition;
use SymPress\Mailer\Provider\ProviderRegistryInterface;
use SymPress\Mailer\Secret\ConnectionSecretResolverInterface;

final readonly class DefaultConnectionHealthChecker implements ConnectionHealthCheckerInterface
{
    public function __construct(
        private ConnectionValidatorInterface $validator,
        private ConnectionSecretResolverInterface $secrets,
        private ProviderRegistryInterface $providers,
    ) {
    }

    #[\Override]
    public function check(ConnectionConfig $connection): ConnectionHealth
    {
        $validation = $this->validator->validate($connection);

        if (!$validation->valid()) {
            return new ConnectionHealth(false, $validation->message());
        }

        $connection = $this->secrets->resolve($connection);
        $definition = $this->providers->get($connection->provider);

        if ($connection->provider === 'cloudflare' && function_exists('wp_safe_remote_get')) {
            return $this->checkCloudflare($connection);
        }

        if ($definition instanceof ProviderDefinition) {
            return $this->configuredHealth($connection, $definition);
        }

        return new ConnectionHealth(true, 'Connection configuration is valid.', ['provider' => $connection->provider]);
    }

    private function checkCloudflare(ConnectionConfig $connection): ConnectionHealth
    {
        $response = wp_safe_remote_get(
            'https://api.cloudflare.com/client/v4/user/tokens/verify',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $connection->apiKey,
                    'Accept'        => 'application/json',
                ],
                'timeout' => 10,
            ],
        );

        if (is_wp_error($response)) {
            return new ConnectionHealth(false, $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code === 200 && is_array($body) && ($body['success'] ?? false) === true) {
            return new ConnectionHealth(true, 'Cloudflare API token is valid.');
        }

        return new ConnectionHealth(false, 'Cloudflare API token could not be verified.', ['status' => $code]);
    }

    private function configuredHealth(ConnectionConfig $connection, ProviderDefinition $definition): ConnectionHealth
    {
        $context = [
            'provider'  => $definition->key,
            'transport' => $definition->transport,
            'key_store' => $connection->keyStore,
        ];

        if ($connection->secretPrefix !== '') {
            $context['secret_prefix'] = $connection->secretPrefix;
        }

        if ($connection->dsn !== '') {
            return new ConnectionHealth(true, 'Symfony DSN is configured. Send a test email to verify delivery.', [...$context, 'mode' => 'dsn']);
        }

        if ($definition->supports('custom_transport')) {
            return new ConnectionHealth(true, $definition->title . ' API transport is configured. Send a test email to verify delivery.', [...$context, 'mode' => 'custom_api']);
        }

        return match ($definition->transport) {
            'smtp' => new ConnectionHealth(true, 'SMTP settings are present for ' . $definition->title . '. Send a test email to verify authentication and delivery.', [...$context, 'mode' => 'smtp']),
            'symfony-bridge' => new ConnectionHealth(true, $definition->title . ' API credentials are present. Send a test email to verify provider access.', [...$context, 'mode' => 'symfony_bridge']),
            'native', 'sendmail' => new ConnectionHealth(true, $definition->title . ' transport is available through the local server.', [...$context, 'mode' => $definition->transport]),
            default => new ConnectionHealth(true, $definition->title . ' configuration is valid.', $context),
        };
    }
}
