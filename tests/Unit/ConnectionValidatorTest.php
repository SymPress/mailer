<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Provider\DefaultProviderRegistry;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;
use SymPress\Mailer\Validation\DefaultConnectionValidator;

final class ConnectionValidatorTest extends TestCase
{
    public function testRequiresSmtpHost(): void
    {
        $result = $this->validator()->validate(
            new ConnectionConfig(id: 'primary', provider: 'smtp'),
        );

        self::assertFalse($result->valid());
        self::assertArrayHasKey('host', $result->errors);
    }

    public function testResolvesRequiredApiKeyFromEnvironment(): void
    {
        putenv('SYMPRESS_MAILER_PRIMARY_API_KEY=env-key');

        try {
            $result = $this->validator()->validate(
                new ConnectionConfig(id: 'primary', provider: 'sendgrid', keyStore: 'env'),
            );

            self::assertTrue($result->valid(), $result->message());
        } finally {
            putenv('SYMPRESS_MAILER_PRIMARY_API_KEY');
        }
    }

    public function testExplicitDsnSatisfiesProviderSpecificRequiredFields(): void
    {
        $result = $this->validator()->validate(
            new ConnectionConfig(
                id: 'primary',
                provider: 'sendgrid',
                dsn: 'smtp://user:secret@smtp.example.test:587',
            ),
        );

        self::assertTrue($result->valid(), $result->message());
    }

    public function testAcceptsCustomApiProviderWithRequiredCredentials(): void
    {
        $result = $this->validator()->validate(
            new ConnectionConfig(id: 'primary', provider: 'cloudflare', apiKey: 'key', tenantId: 'account-id'),
        );

        self::assertTrue($result->valid(), $result->message());
    }

    public function testRequiresCustomApiProviderSpecificFields(): void
    {
        $result = $this->validator()->validate(
            new ConnectionConfig(id: 'primary', provider: 'cloudflare', apiKey: 'key'),
        );

        self::assertFalse($result->valid());
        self::assertArrayHasKey('tenant_id', $result->errors);
    }

    public function testRequiresNamedSmtpProviderCredentials(): void
    {
        $result = $this->validator()->validate(
            new ConnectionConfig(id: 'primary', provider: 'sendlayer'),
        );

        self::assertFalse($result->valid());
        self::assertArrayHasKey('username', $result->errors);
        self::assertArrayHasKey('password', $result->errors);
    }

    public function testRequiresSesRegion(): void
    {
        $result = $this->validator()->validate(
            new ConnectionConfig(id: 'primary', provider: 'ses', apiKey: 'key', apiSecret: 'secret'),
        );

        self::assertFalse($result->valid());
        self::assertArrayHasKey('region', $result->errors);
    }

    public function testRejectsUnsupportedProviderOptionValues(): void
    {
        $result = $this->validator()->validate(
            new ConnectionConfig(id: 'primary', provider: 'smtp2go', apiKey: 'key', region: 'moon'),
        );

        self::assertFalse($result->valid());
        self::assertArrayHasKey('region', $result->errors);
    }

    public function testRequiredSecretsNameCandidateEnvironmentAndConstantKeys(): void
    {
        $result = $this->validator()->validate(
            new ConnectionConfig(id: 'primary', provider: 'sendgrid', keyStore: 'wp_config'),
        );

        self::assertFalse($result->valid());
        self::assertStringContainsString('SYMPRESS_MAILER_PRIMARY_API_KEY', $result->message());
        self::assertStringContainsString('SYMPRESS_MAILER_SENDGRID_API_KEY', $result->message());
    }

    public function testRejectsUnknownSecretStore(): void
    {
        $result = $this->validator()->validate(
            new ConnectionConfig(id: 'primary', provider: 'native', keyStore: 'vault'),
        );

        self::assertFalse($result->valid());
        self::assertArrayHasKey('key_store', $result->errors);
    }

    private function validator(): DefaultConnectionValidator
    {
        return new DefaultConnectionValidator(
            new DefaultProviderRegistry(),
            new EnvironmentConnectionSecretResolver(),
        );
    }
}
