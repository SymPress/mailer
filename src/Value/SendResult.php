<?php

declare(strict_types=1);

namespace SymPress\Mailer\Value;

final readonly class SendResult
{
    public function __construct(
        public bool $accepted,
        public string $logId,
        public string $status,
        public ?string $connectionId = null,
        public ?string $error = null,
    ) {
    }

    public static function sent(string $logId, string $connectionId): self
    {
        return new self(true, $logId, 'sent', $connectionId);
    }

    public static function queued(string $logId, string $reason): self
    {
        return new self(true, $logId, 'queued', null, $reason);
    }

    public static function suppressed(string $reason): self
    {
        return new self(true, 'suppressed', 'suppressed', null, $reason);
    }

    public static function failed(string $logId, string $error): self
    {
        return new self(false, $logId, 'failed', null, $error);
    }
}
