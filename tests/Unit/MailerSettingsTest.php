<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\MailerSettings;

final class MailerSettingsTest extends TestCase
{
    public function testPersistsCoreMailerSettings(): void
    {
        $settings = MailerSettings::fromArray(
            [
                'enabled' => false,
                'connection' => [
                    'id' => 'primary',
                    'provider' => 'smtp',
                    'host' => 'smtp.example.test',
                    'from_email' => 'team@example.test',
                    'force_from' => true,
                ],
                'do_not_send' => true,
                'uninstall_data' => true,
            ],
        );

        $data = $settings->toArray();

        self::assertFalse($data['enabled']);
        self::assertSame('smtp', $settings->defaultConnection()->provider);
        self::assertSame('smtp.example.test', $settings->defaultConnection()->host);
        self::assertSame('team@example.test', $settings->defaultConnection()->fromEmail);
        self::assertTrue($settings->defaultConnection()->forceFrom);
        self::assertTrue($data['do_not_send']);
        self::assertTrue($data['uninstall_data']);
    }

    public function testPostedPrimaryConnectionWinsOverStoredPrimaryConnection(): void
    {
        $settings = MailerSettings::fromArray(
            [
                'connections' => [
                    'primary' => [
                        'id' => 'primary',
                        'provider' => 'native',
                    ],
                ],
                'connection' => [
                    'id' => 'primary',
                    'provider' => 'sendgrid',
                    'api_key' => 'key',
                ],
            ],
        );

        self::assertSame('sendgrid', $settings->defaultConnection()->provider);
        self::assertSame('key', $settings->defaultConnection()->apiKey);
    }
}
