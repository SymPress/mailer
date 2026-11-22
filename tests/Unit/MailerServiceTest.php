<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Application\MailerService;
use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Message\DefaultAttachmentPolicy;
use SymPress\Mailer\Message\NullEmailBodyProcessor;
use SymPress\Mailer\Message\SymfonyEmailFactory;
use SymPress\Mailer\Message\WordPressMail;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;
use SymPress\Mailer\Transport\DsnFactory;
use SymPress\Mailer\Transport\ProviderApiTransportFactory;
use SymPress\Mailer\Transport\SymfonyMailerFactory;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

final class MailerServiceTest extends TestCase
{
    public function testDeliveryFailureFiresActionAndKeepsGenericResult(): void
    {
        $settings = MailerSettings::fromArray(['connection' => ['provider' => 'smtp', 'from_email' => 'site@example.test']]);
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
        $transport = new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection could not be established with host "smtp.example.test:587".');
            }

            public function __toString(): string
            {
                return 'failing://local';
            }
        };
        $secrets = new EnvironmentConnectionSecretResolver();
        $mailer = new MailerService(
            $repository,
            new SymfonyEmailFactory(new NullEmailBodyProcessor(), new DefaultAttachmentPolicy()),
            new SymfonyMailerFactory(new DsnFactory($secrets), new ProviderApiTransportFactory($secrets), $transport),
        );
        $mail = new WordPressMail(to: ['ada@example.test'], subject: 'Hello', message: 'Body');
        $GLOBALS['sympress_mailer_test_actions'] = [];

        $result = $mailer->send($mail);

        self::assertFalse($result->accepted);
        self::assertSame('Mail transport failed.', $result->error);
        $actions = array_values(array_filter(
            $GLOBALS['sympress_mailer_test_actions'],
            static fn (array $action): bool => $action['hook'] === 'sympress_mailer_delivery_failed',
        ));
        self::assertCount(1, $actions);
        [$logId, $error, $actionMail, $connection] = $actions[0]['args'];
        self::assertSame($result->logId, $logId);
        self::assertInstanceOf(TransportException::class, $error);
        self::assertStringContainsString('smtp.example.test:587', $error->getMessage());
        self::assertSame($mail, $actionMail);
        self::assertSame($settings->defaultConnection(), $connection);
    }
}
