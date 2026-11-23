<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Application\MailerInterface;
use SymPress\Mailer\Application\MailerService;
use SymPress\Mailer\Application\TestEmailSender;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Console\MailerStatusCommand;
use SymPress\Mailer\Console\MailerTestCommand;
use SymPress\Mailer\Message\DefaultAttachmentPolicy;
use SymPress\Mailer\Message\NullEmailBodyProcessor;
use SymPress\Mailer\Message\SymfonyEmailFactory;
use SymPress\Mailer\Message\WordPressMail;
use SymPress\Mailer\Provider\DefaultProviderRegistry;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;
use SymPress\Mailer\Transport\DsnFactory;
use SymPress\Mailer\Transport\ProviderApiTransportFactory;
use SymPress\Mailer\Transport\SymfonyMailerFactory;
use SymPress\Mailer\Validation\DefaultConnectionValidator;
use SymPress\Mailer\Validation\SmtpConnectionProbe;
use SymPress\Mailer\Value\SendResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class ConsoleCommandsTest extends TestCase
{
    public function testTestCommandRejectsAnInvalidAddressWithoutSending(): void
    {
        $transport = $this->recordingTransport();
        $tester = $this->testCommand(['connection' => ['provider' => 'smtp']], $transport);

        self::assertSame(Command::INVALID, $tester->execute(['to' => 'not-an-address']));
        self::assertStringContainsString('"not-an-address" is not a valid email address', $tester->getDisplay());
        self::assertSame(0, $transport->sent);
    }

    public function testTestCommandReportsSentMail(): void
    {
        $transport = $this->recordingTransport();
        $tester = $this->testCommand(['connection' => ['provider' => 'smtp', 'from_email' => 'site@example.test']], $transport);

        self::assertSame(Command::SUCCESS, $tester->execute(['to' => 'ada@example.test']));
        self::assertStringContainsString('Status: sent. Test email sent to ada@example.test.', $tester->getDisplay());
        self::assertSame(1, $transport->sent);
    }

    public function testTestCommandMakesSuppressedMailObvious(): void
    {
        $transport = $this->recordingTransport();
        $tester = $this->testCommand(['do_not_send' => true, 'connection' => ['provider' => 'smtp']], $transport);

        self::assertSame(Command::SUCCESS, $tester->execute(['to' => 'ada@example.test']));
        self::assertStringContainsString('Status: suppressed. "Do not send" is on', $tester->getDisplay());
        self::assertSame(0, $transport->sent);
    }

    public function testTestCommandPrintsTheRedactedFailureReason(): void
    {
        $transport = new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Authentication failed with password "test-canary" on "smtp.example.test:587".');
            }

            public function __toString(): string
            {
                return 'failing://local';
            }
        };
        $tester = $this->testCommand(['connection' => ['provider' => 'smtp', 'from_email' => 'site@example.test', 'password' => 'test-canary']], $transport);

        self::assertSame(Command::FAILURE, $tester->execute(['to' => 'ada@example.test']));
        self::assertStringContainsString('Test email failed: Authentication failed with password "***" on "smtp.example.test:587".', $tester->getDisplay());
        self::assertStringNotContainsString('test-canary', $tester->getDisplay());
    }

    public function testTestCommandPrintsTheReasonOfAnAttemptThatAnExtensionQueuedForARetry(): void
    {
        $mailer = new class implements MailerInterface {
            public function send(WordPressMail $mail): SendResult
            {
                $connection = ConnectionConfig::fromArray(['provider' => 'smtp', 'password' => 'test-canary']);
                do_action('sympress_mailer_delivery_failed', 'log-1', new TransportException('Rejected password "test-canary".'), $mail, $connection);

                return SendResult::queued('log-1', 'transport_retry');
            }
        };
        $tester = new CommandTester(new MailerTestCommand(new TestEmailSender($mailer, new EnvironmentConnectionSecretResolver())));

        self::assertSame(Command::SUCCESS, $tester->execute(['to' => 'ada@example.test']));
        self::assertStringContainsString('has NOT been sent yet. The last attempt failed: Rejected password "***".', $tester->getDisplay());
    }

    public function testStatusCommandReportsAValidConfiguration(): void
    {
        $tester = $this->statusCommand(['connection' => ['provider' => 'smtp', 'host' => 'smtp.example.test', 'from_email' => 'site@example.test', 'from_name' => 'Site', 'force_from' => true]]);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertMatchesRegularExpression('/Enabled:\s+yes/', $display);
        self::assertMatchesRegularExpression('/Do not send:\s+no/', $display);
        self::assertMatchesRegularExpression('/Provider:\s+smtp/', $display);
        self::assertMatchesRegularExpression('/Secret source:\s+encrypted_option/', $display);
        self::assertMatchesRegularExpression('/From email:\s+site@example.test/', $display);
        self::assertMatchesRegularExpression('/Force From email:\s+yes/', $display);
        self::assertStringContainsString('Configuration: valid', $display);
    }

    public function testStatusCommandListsValidationErrors(): void
    {
        $tester = $this->statusCommand(['connection' => ['provider' => 'smtp']]);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Configuration: invalid', $tester->getDisplay());
        self::assertStringContainsString('- host: Required for Other SMTP.', $tester->getDisplay());
    }

    public function testStatusCommandReportsUnreadableSettings(): void
    {
        $tester = $this->statusCommand(new \RuntimeException('Configure a private mailer encryption key of at least 32 bytes or valid WordPress salts.'));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Mailer settings cannot be read: Configure a private mailer encryption key', $tester->getDisplay());
    }

    public function testStatusCommandNeverPrintsSecrets(): void
    {
        $tester = $this->statusCommand(['connection' => [
            'provider'   => 'smtp',
            'dsn'        => 'smtp://dsn-user-canary:dsn-pass-canary@127.0.0.1:1',
            'username'   => 'username-canary',
            'password'   => 'password-canary',
            'api_key'    => 'api-key-canary',
            'api_secret' => 'api-secret-canary',
        ]]);

        $tester->execute(['--probe' => true]);

        foreach (['dsn-user-canary', 'dsn-pass-canary', 'username-canary', 'password-canary', 'api-key-canary', 'api-secret-canary'] as $secret) {
            self::assertStringNotContainsString($secret, $tester->getDisplay());
        }
    }

    public function testStatusCommandFailsWhenTheProbeFails(): void
    {
        $tester = $this->statusCommand(['connection' => ['provider' => 'smtp', 'host' => '127.0.0.1', 'port' => 1]]);

        self::assertSame(Command::FAILURE, $tester->execute(['--probe' => true]));
        self::assertStringContainsString('Configuration: valid', $tester->getDisplay());
        self::assertMatchesRegularExpression('/Connection check: .*127\.0\.0\.1:1/', $tester->getDisplay());
    }

    /** @param array<string, mixed> $settings */
    private function testCommand(array $settings, TransportInterface $transport): CommandTester
    {
        $secrets = new EnvironmentConnectionSecretResolver();
        $mailer = new MailerService(
            $this->repository(MailerSettings::fromArray($settings)),
            new SymfonyEmailFactory(new NullEmailBodyProcessor(), new DefaultAttachmentPolicy()),
            new SymfonyMailerFactory(new DsnFactory($secrets), new ProviderApiTransportFactory($secrets), $transport),
        );

        return new CommandTester(new MailerTestCommand(new TestEmailSender($mailer, $secrets)));
    }

    /** @param array<string, mixed>|\Throwable $settings */
    private function statusCommand(array|\Throwable $settings): CommandTester
    {
        $secrets = new EnvironmentConnectionSecretResolver();

        return new CommandTester(new MailerStatusCommand(
            $this->repository(is_array($settings) ? MailerSettings::fromArray($settings) : $settings),
            new DefaultConnectionValidator(new DefaultProviderRegistry(), $secrets),
            new SmtpConnectionProbe(new SymfonyMailerFactory(new DsnFactory($secrets), new ProviderApiTransportFactory($secrets)), $secrets),
        ));
    }

    private function repository(MailerSettings|\Throwable $settings): SettingsRepositoryInterface
    {
        return new class($settings) implements SettingsRepositoryInterface {
            public function __construct(
                private MailerSettings|\Throwable $settings,
            ) {
            }

            public function get(): MailerSettings
            {
                return $this->settings instanceof \Throwable ? throw $this->settings : $this->settings;
            }

            public function save(MailerSettings $settings): void
            {
                $this->settings = $settings;
            }
        };
    }

    private function recordingTransport(): AbstractTransport
    {
        return new class extends AbstractTransport {
            public int $sent = 0;

            protected function doSend(SentMessage $message): void
            {
                ++$this->sent;
            }

            public function __toString(): string
            {
                return 'recording://local';
            }
        };
    }
}
