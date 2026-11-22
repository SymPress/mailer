<?php

declare(strict_types=1);

namespace SymPress\Mailer\Validation;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Secret\ConnectionSecretResolverInterface;
use SymPress\Mailer\Support\FailureReason;
use SymPress\Mailer\Transport\SymfonyMailerFactory;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/** Connects to an SMTP server (EHLO, STARTTLS, AUTH) without sending mail; kept out of save-time health checks. */
final readonly class SmtpConnectionProbe
{
    private const float TIMEOUT_SECONDS = 10.0;

    public function __construct(
        private SymfonyMailerFactory $mailerFactory,
        private ConnectionSecretResolverInterface $secrets,
    ) {
    }

    public function probe(ConnectionConfig $connection): ConnectionHealth
    {
        $transport = null;

        try {
            $transport = $this->mailerFactory->transport($connection);

            if (!$transport instanceof SmtpTransport) {
                return new ConnectionHealth(true, 'This connection type cannot be checked without sending. Send a test email instead.', ['checked' => false]);
            }

            $stream = $transport->getStream();

            if ($stream instanceof SocketStream) {
                $stream->setTimeout(self::TIMEOUT_SECONDS);
            }

            $transport->start();
            // Symfony skips AUTH silently when the server does not offer it, so only the transcript tells whether credentials were checked.
            $authenticated = str_contains($stream->getDebug(), '> AUTH ');
            $message = match (true) {
                $authenticated => 'SMTP server accepted the connection and credentials.',
                $transport instanceof EsmtpTransport && $transport->getUsername() !== '' => 'SMTP server accepted the connection but did not offer authentication, so the credentials were not checked.',
                default => 'SMTP server accepted the connection.',
            };

            return new ConnectionHealth(true, $message, ['checked' => true, 'authenticated' => $authenticated]);
        } catch (\Throwable $error) {
            return new ConnectionHealth(false, $this->reason($error, $connection), ['checked' => true]);
        } finally {
            if ($transport instanceof SmtpTransport) {
                // stop() sends QUIT only after a full start(); terminate() also closes a socket left by a failed handshake.
                $transport->stop();
                $transport->getStream()->terminate();
            }
        }
    }

    private function reason(\Throwable $error, ConnectionConfig $connection): string
    {
        try {
            return FailureReason::redact($error->getMessage(), $this->secrets->resolve($connection));
        } catch (\Throwable) {
            // Without resolved secrets the message cannot be redacted safely, so show none.
            return 'SMTP connection check failed.';
        }
    }
}
