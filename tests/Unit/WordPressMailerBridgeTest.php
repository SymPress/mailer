<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Application\MailerInterface;
use SymPress\Mailer\Application\MailerService;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Config\DeliverySettingsRepositoryInterface;
use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Hook\WordPressMailerBridge;
use SymPress\Mailer\Message\DefaultAttachmentPolicy;
use SymPress\Mailer\Message\NullEmailBodyProcessor;
use SymPress\Mailer\Message\SymfonyEmailFactory;
use SymPress\Mailer\Message\WordPressMail;
use SymPress\Mailer\Message\WordPressMailParser;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;
use SymPress\Mailer\Transport\DsnFactory;
use SymPress\Mailer\Transport\ProviderApiTransportFactory;
use SymPress\Mailer\Transport\SymfonyMailerFactory;
use SymPress\Mailer\Value\SendResult;

final class WordPressMailerBridgeTest extends TestCase
{
    public function testBridgeAndServiceUseTheOptionalDeliveryView(): void
    {
        $repository = new class implements DeliverySettingsRepositoryInterface {
            public int $editingReads = 0;
            public int $deliveryReads = 0;

            public function get(): MailerSettings
            {
                ++$this->editingReads;
                return new MailerSettings(enabled: false);
            }

            public function getForDelivery(): MailerSettings
            {
                ++$this->deliveryReads;
                return new MailerSettings(doNotSend: true);
            }

            public function save(MailerSettings $settings): void
            {
            }
        };
        $secrets = new EnvironmentConnectionSecretResolver();
        $mailer = new MailerService($repository, new SymfonyEmailFactory(new NullEmailBodyProcessor(), new DefaultAttachmentPolicy()), new SymfonyMailerFactory(new DsnFactory($secrets), new ProviderApiTransportFactory($secrets)));
        $bridge = new WordPressMailerBridge($repository, new WordPressMailParser(), $mailer);

        self::assertTrue($bridge->send(null, ['to' => 'ada@example.test', 'subject' => 'Suppressed delivery', 'message' => 'Hello']));
        self::assertSame('suppressed', $mailer->send(new WordPressMail(['ada@example.test'], 'Service delivery', 'Hello'))->status);
        self::assertSame(0, $repository->editingReads);
        self::assertSame(2, $repository->deliveryReads);
    }

    public function testHookInputReachesTheConfiguredMailer(): void
    {
        $settings = new class implements SettingsRepositoryInterface {
            public function get(): MailerSettings
            {
                return new MailerSettings();
            }

            public function save(MailerSettings $settings): void
            {
            }
        };
        $mailer = new class implements MailerInterface {
            public ?WordPressMail $mail = null;

            public function send(WordPressMail $mail): SendResult
            {
                $this->mail = $mail;

                return SendResult::sent('log-1', 'primary');
            }
        };
        $bridge = new WordPressMailerBridge($settings, new WordPressMailParser(), $mailer);

        $result = $bridge->send(null, [
            'to' => 'ada@example.test',
            'subject' => 'Welcome',
            'message' => 'Hello',
            'headers' => ['Content-Type: text/html; charset=UTF-8'],
        ]);

        self::assertTrue($result);
        self::assertInstanceOf(WordPressMail::class, $mailer->mail);
        self::assertSame(['ada@example.test'], $mailer->mail->to);
        self::assertSame('Welcome', $mailer->mail->subject);
        self::assertSame('text/html', $mailer->mail->contentType);
        self::assertSame('UTF-8', $mailer->mail->charset);
    }

    public function testHookInputReachesSymfonyNullTransport(): void
    {
        $settings = new MailerSettings(
            connections: [
                'primary' => new ConnectionConfig(
                    id: 'primary',
                    dsn: 'null://null',
                    fromEmail: 'mailer@example.test',
                ),
            ],
        );
        $repository = new class($settings) implements SettingsRepositoryInterface {
            public function __construct(
                private MailerSettings $settings,
            ) {
            }

            public function get(): MailerSettings
            {
                return $this->settings;
            }

            public function save(MailerSettings $settings): void
            {
                $this->settings = $settings;
            }
        };
        $secrets = new EnvironmentConnectionSecretResolver();
        $mailer = new MailerService(
            $repository,
            new SymfonyEmailFactory(new NullEmailBodyProcessor(), new DefaultAttachmentPolicy()),
            new SymfonyMailerFactory(
                new DsnFactory($secrets),
                new ProviderApiTransportFactory($secrets),
            ),
        );
        $bridge = new WordPressMailerBridge($repository, new WordPressMailParser(), $mailer);

        self::assertTrue($bridge->send(null, [
            'to'      => 'ada@example.test',
            'subject' => 'Transport smoke',
            'message' => 'Hello',
        ]));
    }
}
