<?php

declare(strict_types=1);

namespace SymPress\Mailer\Validation;

use SymPress\Mailer\Config\ConnectionConfig;

interface ConnectionValidatorInterface
{
    public function validate(ConnectionConfig $connection): ConnectionValidationResult;
}
