<?php

declare(strict_types=1);

namespace SymPress\Mailer\Transport;

use SymPress\Mailer\Config\ConnectionConfig;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

final readonly class SymfonyMailerFactory
{
    public function __construct(
        private DsnFactory $dsnFactory,
    ) {
    }

    public function create(ConnectionConfig $connection): MailerInterface
    {
        return new Mailer(Transport::fromDsn($this->dsnFactory->create($connection)));
    }
}
