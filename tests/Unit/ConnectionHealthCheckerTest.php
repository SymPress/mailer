<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Provider\DefaultProviderRegistry;
use SymPress\Mailer\Secret\ConnectionSecretResolverInterface;
use SymPress\Mailer\Validation\ConnectionHealth;
use SymPress\Mailer\Validation\ConnectionValidationResult;
use SymPress\Mailer\Validation\ConnectionValidatorInterface;
use SymPress\Mailer\Validation\DefaultConnectionHealthChecker;

final class ConnectionHealthCheckerTest extends TestCase
{
    public function testReportsCustomApiTransportHealthContext(): void
    {
        $health = $this->checker()->check(
            new ConnectionConfig(id: 'primary', provider: 'smtp2go', apiKey: 'api-key', region: 'eu'),
        );

        self::assertTrue($health->healthy);
        self::assertSame('SMTP2GO API transport is configured. Send a test email to verify delivery.', $health->message);
        self::assertSame('custom_api', $health->context['mode']);
        self::assertSame('custom-api', $health->context['transport']);
    }

    public function testReportsSmtpHealthContext(): void
    {
        $health = $this->checker()->check(
            new ConnectionConfig(id: 'primary', provider: 'smtp', host: 'smtp.example.test', username: 'user', password: 'secret'),
        );

        self::assertTrue($health->healthy);
        self::assertSame('SMTP settings are present for Other SMTP. Send a test email to verify authentication and delivery.', $health->message);
        self::assertSame('smtp', $health->context['mode']);
    }

    public function testReturnsValidationErrorsBeforeHealthContext(): void
    {
        $health = $this->checker(new ConnectionValidationResult(['api_key' => ['Required.']]))->check(
            new ConnectionConfig(id: 'primary', provider: 'smtp2go'),
        );

        self::assertFalse($health->healthy);
        self::assertSame('api_key: Required.', $health->message);
    }

    private function checker(?ConnectionValidationResult $validation = null): DefaultConnectionHealthChecker
    {
        return new DefaultConnectionHealthChecker(
            new class($validation ?? new ConnectionValidationResult()) implements ConnectionValidatorInterface {
                public function __construct(private ConnectionValidationResult $validation)
                {
                }

                public function validate(ConnectionConfig $connection): ConnectionValidationResult
                {
                    return $this->validation;
                }
            },
            new class implements ConnectionSecretResolverInterface {
                public function resolve(ConnectionConfig $connection): ConnectionConfig
                {
                    return $connection;
                }
            },
            new DefaultProviderRegistry(),
        );
    }
}
