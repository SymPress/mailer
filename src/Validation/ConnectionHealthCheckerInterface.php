<?php

declare(strict_types=1);

namespace SymPress\Mailer\Validation;

use SymPress\Mailer\Config\ConnectionConfig;

interface ConnectionHealthCheckerInterface
{
    public function check(ConnectionConfig $connection): ConnectionHealth;
}
