<?php

declare(strict_types=1);

namespace SymPress\Mailer\Validation;

final readonly class ConnectionHealth
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public bool $healthy,
        public string $message,
        public array $context = [],
    ) {
    }
}
