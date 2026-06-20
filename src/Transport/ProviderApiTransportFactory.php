<?php

declare(strict_types=1);

namespace SymPress\Mailer\Transport;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Secret\ConnectionSecretResolverInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

final readonly class ProviderApiTransportFactory
{
    public function __construct(
        private ConnectionSecretResolverInterface $secrets,
    ) {
    }

    public function create(ConnectionConfig $connection): ?TransportInterface
    {
        $connection = $this->secrets->resolve($connection);

        if (!ProviderApiTransport::supportsProvider($connection->provider)) {
            return null;
        }

        return new ProviderApiTransport($connection);
    }
}
