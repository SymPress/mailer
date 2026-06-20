<?php

declare(strict_types=1);

namespace SymPress\Mailer\Transport;

use SymPress\Mailer\Config\ConnectionConfig;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

final readonly class SymfonyMailerFactory
{
    public function __construct(
        private DsnFactory $dsnFactory,
        private ProviderApiTransportFactory $providerApiTransports,
    ) {
    }

    public function create(ConnectionConfig $connection): MailerInterface
    {
        return new Mailer($this->transport($connection));
    }

    public function transport(ConnectionConfig $connection): TransportInterface
    {
        return $this->providerApiTransports->create($connection)
            ?? Transport::fromDsn($this->dsnFactory->create($connection));
    }
}
