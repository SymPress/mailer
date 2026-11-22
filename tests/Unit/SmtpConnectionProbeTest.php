<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;
use SymPress\Mailer\Transport\DsnFactory;
use SymPress\Mailer\Transport\ProviderApiTransportFactory;
use SymPress\Mailer\Transport\SymfonyMailerFactory;
use SymPress\Mailer\Validation\SmtpConnectionProbe;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class SmtpConnectionProbeTest extends TestCase
{
    public function testUnreachableServerFailsWithoutExposingThePassword(): void
    {
        $health = $this->probe()->probe(
            new ConnectionConfig(id: 'primary', provider: 'smtp', host: '127.0.0.1', port: 1, username: 'user', password: 'probe-canary'),
        );

        self::assertFalse($health->healthy);
        self::assertStringContainsString('127.0.0.1:1', $health->message);
        self::assertStringNotContainsString('probe-canary', $health->message);
    }

    public function testNonSmtpTransportIsReportedAsNotChecked(): void
    {
        $health = $this->probe()->probe(new ConnectionConfig(id: 'primary', provider: 'sendmail'));

        self::assertTrue($health->healthy);
        self::assertFalse($health->context['checked']);
        self::assertSame('This connection type cannot be checked without sending. Send a test email instead.', $health->message);
    }

    public function testAcceptedCredentialsAreReported(): void
    {
        $stream = new FakeSmtpStream("220 fake ESMTP\r\n250-fake\r\n250 AUTH PLAIN\r\n235 ok\r\n221 bye\r\n");
        $transport = new EsmtpTransport('smtp.example.test', 587, false, null, null, $stream);
        $transport->setUsername('user');
        $transport->setPassword('probe-canary');

        $health = $this->probe($transport)->probe(new ConnectionConfig(id: 'primary', provider: 'smtp'));

        self::assertTrue($health->healthy);
        self::assertSame('SMTP server accepted the connection and credentials.', $health->message);
        self::assertStringContainsString("QUIT\r\n", $stream->written());
        self::assertTrue($stream->terminated);
    }

    public function testServerWithoutAuthIsReportedAsConnectionOnly(): void
    {
        $stream = new FakeSmtpStream("220 fake ESMTP\r\n250 fake\r\n221 bye\r\n");

        $health = $this->probe(new EsmtpTransport('smtp.example.test', 587, false, null, null, $stream))
            ->probe(new ConnectionConfig(id: 'primary', provider: 'smtp'));

        self::assertTrue($health->healthy);
        self::assertSame('SMTP server accepted the connection.', $health->message);
        self::assertFalse($health->context['authenticated']);
    }

    private function probe(?TransportInterface $transport = null): SmtpConnectionProbe
    {
        $secrets = new EnvironmentConnectionSecretResolver();

        return new SmtpConnectionProbe(
            new SymfonyMailerFactory(new DsnFactory($secrets), new ProviderApiTransportFactory($secrets), $transport),
            $secrets,
        );
    }
}

/** Replays canned SMTP replies; the setters stand in for the SocketStream methods EsmtpTransport calls. */
final class FakeSmtpStream extends AbstractStream
{
    public bool $terminated = false;
    private string $sent = '';

    public function __construct(private readonly string $replies)
    {
    }

    public function initialize(): void
    {
        $out = fopen('php://memory', 'w+');
        if ($out === false) {
            throw new \RuntimeException('Could not open the fake reply stream.');
        }

        fwrite($out, $this->replies);
        rewind($out);
        $this->out = $out;
        $this->in = fopen('php://memory', 'w+') ?: null;
    }

    public function terminate(): void
    {
        if (is_resource($this->in)) {
            rewind($this->in);
            $this->sent .= stream_get_contents($this->in);
            $this->terminated = true;
        }

        parent::terminate();
    }

    public function written(): string
    {
        return $this->sent;
    }

    public function setHost(string $host): void
    {
    }

    public function setPort(int $port): void
    {
    }

    public function disableTls(): void
    {
    }

    public function isTls(): bool
    {
        return false;
    }

    protected function getReadConnectionDescription(): string
    {
        return 'fake';
    }
}
