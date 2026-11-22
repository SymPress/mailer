<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Support\FailureReason;

final class FailureReasonTest extends TestCase
{
    public function testRawAndUrlEncodedSecretsAreReplaced(): void
    {
        $connection = new ConnectionConfig(
            id: 'primary',
            dsn: 'smtp://user:p%40ss w0rd@smtp.example.test:587',
            password: 'p@ss w0rd',
            apiKey: 'key-canary',
            apiSecret: 'secret/canary',
        );
        $message = "Failed for smtp://user:p%40ss w0rd@smtp.example.test:587\nAUTH p@ss w0rd p%40ss%20w0rd key-canary secret%2Fcanary secret/canary";

        $reason = FailureReason::redact($message, $connection);

        self::assertSame('Failed for *** AUTH *** *** *** *** ***', $reason);
    }

    public function testCredentialsInsideADsnAreReplacedOnTheirOwn(): void
    {
        $connection = new ConnectionConfig(
            id: 'primary',
            dsn: 'failover(sendgrid+api://api-canary@default smtp://mailbox:pa%2Fss-canary@smtp.example.test:587)',
        );

        $reason = FailureReason::redact('401 for key api-canary; AUTH failed for mailbox with pa/ss-canary or pa%2Fss-canary', $connection);

        self::assertSame('401 for key ***; AUTH failed for *** with *** or ***', $reason);
    }

    public function testMessageIsCollapsedToOneLineAndCapped(): void
    {
        $reason = FailureReason::redact("  first\r\n\tsecond  " . str_repeat('x', 400), new ConnectionConfig(id: 'primary'));

        self::assertStringStartsWith('first second x', $reason);
        self::assertStringNotContainsString("\n", $reason);
        self::assertSame(300, mb_strlen($reason));
        self::assertStringEndsWith('…', $reason);
    }

    public function testMessageWithoutSecretsIsUntouched(): void
    {
        $message = 'Connection could not be established with host "smtp.example.test:587".';

        self::assertSame($message, FailureReason::redact($message, new ConnectionConfig(id: 'primary', password: 'unused-canary')));
    }
}
