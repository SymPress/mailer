<?php

declare(strict_types=1);

namespace SymPress\Mailer\Secret;

use SymPress\Mailer\Config\ConnectionConfig;

interface ConnectionSecretResolverInterface
{
    public function resolve(ConnectionConfig $connection): ConnectionConfig;
}
