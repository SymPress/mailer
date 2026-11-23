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
        // An error can echo only the credential part of a DSN, so mask each user, key and password in it too.
        preg_match_all('#://([^:@/\s]+)(?::([^@/\s]+))?@#', $connection->dsn, $userInfo);

        foreach ([$connection->dsn, $connection->password, $connection->apiKey, $connection->apiSecret, $connection->username, $connection->domain, $connection->tenantId, ...$userInfo[1], ...$userInfo[2]] as $secret) {
            if ($secret === '') {
                continue;
            }

            $secrets[] = $secret;
            $secrets[] = rawurlencode($secret);
            $secrets[] = rawurldecode($secret);
        }

        // Longest first, so a DSN is masked whole before the password inside it.
        usort($secrets, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $line = trim((string) preg_replace('/\s+/', ' ', str_replace($secrets, '***', $message)));

        return mb_strlen($line) > self::MAX_LENGTH ? mb_substr($line, 0, self::MAX_LENGTH - 1) . '…' : $line;
    }
}
