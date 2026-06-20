<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Import\ConnectionImportService;

final class ConnectionImportServiceTest extends TestCase
{
    public function testMapsWpMailSmtpSendgridSettings(): void
    {
        $candidate = (new ConnectionImportService())->fromWpMailSmtp(
            [
                'mail' => [
                    'mailer' => 'sendgrid',
                    'from_email' => 'site@example.test',
                    'from_name' => 'Site',
                    'from_email_force' => 1,
                ],
                'sendgrid' => [
                    'api_key' => 'sg-key',
                ],
            ],
        );

        self::assertNotNull($candidate);
        self::assertSame('wp-mail-smtp', $candidate->source);
        self::assertSame('sendgrid', $candidate->connection->provider);
        self::assertSame('sg-key', $candidate->connection->apiKey);
        self::assertTrue($candidate->connection->forceFrom);
    }

    public function testWpMailSmtpProviderImportsUseRegistryDefaults(): void
    {
        $candidate = (new ConnectionImportService())->fromWpMailSmtp(
            [
                'mail' => [
                    'mailer' => 'smtp2go',
                ],
                'smtp2go' => [
                    'api_key' => 'smtp2go-key',
                ],
            ],
        );

        self::assertNotNull($candidate);
        self::assertSame('smtp2go', $candidate->connection->provider);
        self::assertSame('smtp2go-key', $candidate->connection->apiKey);
        self::assertSame('global', $candidate->connection->region);
    }

    public function testMapsEasyWpSmtpSettings(): void
    {
        $candidate = (new ConnectionImportService())->fromEasyWpSmtp(
            [
                'from_email_field' => 'site@example.test',
                'from_name_field' => 'Site',
                'smtp_settings' => [
                    'host' => 'smtp.example.test',
                    'port' => '2525',
                    'username' => 'user',
                    'password' => 'secret',
                    'type_encryption' => 'tls',
                ],
            ],
        );

        self::assertNotNull($candidate);
        self::assertSame('easy-wp-smtp', $candidate->source);
        self::assertSame('smtp.example.test', $candidate->connection->host);
        self::assertSame(2525, $candidate->connection->port);
    }

    public function testMapsPostSmtpSettings(): void
    {
        $candidate = (new ConnectionImportService())->fromPostSmtp(
            [
                'sender_email' => 'team@example.test',
                'sender_name' => 'Team',
                'hostname' => 'smtp.post.example.test',
                'port' => '465',
                'username' => 'post-user',
                'password' => 'post-secret',
                'security_type' => 'ssl',
            ],
        );

        self::assertNotNull($candidate);
        self::assertSame('post-smtp', $candidate->source);
        self::assertSame('smtp', $candidate->connection->provider);
        self::assertSame('smtp.post.example.test', $candidate->connection->host);
        self::assertSame(465, $candidate->connection->port);
        self::assertSame('post-user', $candidate->connection->username);
        self::assertSame('post-secret', $candidate->connection->password);
        self::assertSame('ssl', $candidate->connection->encryption);
    }

    public function testMapsFluentSmtpDefaultConnection(): void
    {
        $candidate = (new ConnectionImportService())->fromFluentSmtp(
            [
                'misc' => [
                    'default_connection' => 'sendgrid-main',
                ],
                'connections' => [
                    'fallback' => [
                        'provider_settings' => [
                            'provider' => 'smtp',
                            'host' => 'smtp.fallback.test',
                        ],
                    ],
                    'sendgrid-main' => [
                        'provider_settings' => [
                            'provider' => 'sendgrid',
                            'sender_email' => 'site@example.test',
                            'sender_name' => 'Site',
                            'force_from_email' => 'yes',
                            'force_from_name' => 'yes',
                            'api_key' => 'sg-key',
                        ],
                    ],
                ],
            ],
        );

        self::assertNotNull($candidate);
        self::assertSame('fluent-smtp', $candidate->source);
        self::assertSame('sendgrid', $candidate->connection->provider);
        self::assertSame('sg-key', $candidate->connection->apiKey);
        self::assertSame('site@example.test', $candidate->connection->fromEmail);
        self::assertTrue($candidate->connection->forceFrom);
        self::assertTrue($candidate->connection->forceFromName);
    }

    public function testMapsFluentSmtpWpConfigCloudflareConnection(): void
    {
        $candidate = (new ConnectionImportService())->fromFluentSmtp(
            [
                'connections' => [
                    'cloudflare' => [
                        'provider_settings' => [
                            'provider' => 'cloudflare',
                            'sender_email' => 'postmaster@example.test',
                            'api_key' => '',
                            'account_id' => 'account-123',
                            'key_store' => 'wp_config',
                        ],
                    ],
                ],
            ],
        );

        self::assertNotNull($candidate);
        self::assertSame('cloudflare', $candidate->connection->provider);
        self::assertSame('account-123', $candidate->connection->tenantId);
        self::assertSame('wp_config', $candidate->connection->keyStore);
        self::assertSame('FLUENTMAIL_CLOUDFLARE', $candidate->connection->secretPrefix);
    }

    public function testSkipsEncryptedFluentSmtpSettingsWhenFluentDecryptorIsUnavailable(): void
    {
        $candidate = (new ConnectionImportService())->fromFluentSmtp(
            [
                'use_encrypt' => 'yes',
                'connections' => [
                    'sendgrid' => [
                        'provider_settings' => [
                            'provider' => 'sendgrid',
                            'api_key' => 'encrypted-value',
                        ],
                    ],
                ],
            ],
        );

        self::assertNull($candidate);
    }
}
