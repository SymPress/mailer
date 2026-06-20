<?php

declare(strict_types=1);

namespace SymPress\Mailer\Import;

use SymPress\Mailer\Config\ConnectionConfig;

final readonly class ConnectionImportCandidate
{
    public function __construct(
        public string $source,
        public string $title,
        public string $description,
        public ConnectionConfig $connection,
    ) {
    }
}
