<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Config\WordPressSettingsRepository;

final class WordPressSettingsRepositoryTest extends TestCase
{
    public function testEncryptedOptionSecretsRoundTripThroughWordPressOptions(): void
    {
        $this->defineEncryptionConstants();
        $GLOBALS['sympress_mailer_test_options'] = [];

        $repository = new WordPressSettingsRepository('sympress_mailer_settings_test');
        $repository->save(
            new MailerSettings(
                defaultConnection: 'primary',
                connections: [
                    'primary' => new ConnectionConfig(
                        id: 'primary',
                        provider: 'smtp',
                        username: 'smtp-user',
                        password: 'smtp-secret',
                        apiKey: 'api-secret',
                        keyStore: 'encrypted_option',
                    ),
                ],
            ),
        );

        $stored = $GLOBALS['sympress_mailer_test_options']['sympress_mailer_settings_test'] ?? null;

        self::assertIsArray($stored);
        self::assertStringStartsWith('enc:v1:', $stored['connections']['primary']['username']);
        self::assertStringStartsWith('enc:v1:', $stored['connections']['primary']['password']);
        self::assertStringStartsWith('enc:v1:', $stored['connections']['primary']['api_key']);

        $settings = $repository->get();

        self::assertSame('smtp-user', $settings->defaultConnection()->username);
        self::assertSame('smtp-secret', $settings->defaultConnection()->password);
        self::assertSame('api-secret', $settings->defaultConnection()->apiKey);
    }

    public function testPlainOptionSecretsStayReadableInStoredOptions(): void
    {
        $GLOBALS['sympress_mailer_test_options'] = [];

        $repository = new WordPressSettingsRepository('sympress_mailer_plain_settings_test');
        $repository->save(
            new MailerSettings(
                connections: [
                    'primary' => new ConnectionConfig(
                        id: 'primary',
                        provider: 'smtp',
                        username: 'plain-user',
                        password: 'plain-secret',
                    ),
                ],
            ),
        );

        $stored = $GLOBALS['sympress_mailer_test_options']['sympress_mailer_plain_settings_test'] ?? null;

        self::assertIsArray($stored);
        self::assertSame('plain-user', $stored['connections']['primary']['username']);
        self::assertSame('plain-secret', $stored['connections']['primary']['password']);
    }

    private function defineEncryptionConstants(): void
    {
        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'] as $constant) {
            if (defined($constant)) {
                continue;
            }

            define($constant, 'sympress-mailer-test-' . strtolower($constant));
        }
    }
}
