<?php

declare(strict_types=1);

namespace SymPress\Mailer\Support;

use SymPress\Mailer\Config\ConnectionConfig;

final class FailureReason
{
    private const int MAX_LENGTH = 300;

    /** Turns a delivery error into a single display line without the connection's credentials. */
    public static function redact(string $message, ConnectionConfig $connection): string
    {
        $secrets = [];

        foreach ([$connection->dsn, $connection->password, $connection->apiKey, $connection->apiSecret] as $secret) {
            if ($secret === '') {
                continue;
            }

            $secrets[] = $secret;
            $secrets[] = rawurlencode($secret);
        }

        // Longest first, so a DSN is masked whole before the password inside it.
        usort($secrets, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $line = trim((string) preg_replace('/\s+/', ' ', str_replace($secrets, '***', $message)));

        return mb_strlen($line) > self::MAX_LENGTH ? mb_substr($line, 0, self::MAX_LENGTH - 1) . '…' : $line;
    }
}
