<?php

declare(strict_types=1);

namespace SymPress\Mailer\Application;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Message\WordPressMail;
use SymPress\Mailer\Secret\ConnectionSecretResolverInterface;
use SymPress\Mailer\Support\FailureReason;
use SymPress\Mailer\Value\SendResult;

final readonly class TestEmailSender
{
    public function __construct(
        private MailerInterface $mailer,
        private ?ConnectionSecretResolverInterface $secretResolver = null,
    ) {
    }

    public function send(string $to): SendResult
    {
        return $this->mailer->send(
            new WordPressMail(
                to: [$to],
                subject: 'SymPress Mailer test email',
                message: '<p>This is a SymPress Mailer test email.</p>',
                contentType: 'text/html',
                source: 'sympress-mailer',
            ),
        );
    }

    /**
     * Sends the test email and returns the failure reason with the connection's credentials masked.
     *
     * @return array{SendResult, string} the result and the redacted reason, '' when none is available
     */
    public function sendWithFailureReason(string $to): array
    {
        $reason = '';
        $capture = function (string $logId, \Throwable $error, WordPressMail $mail, ConnectionConfig $connection) use (&$reason): void {
            try {
                $reason = $this->secretResolver !== null
                    ? FailureReason::redact($error->getMessage(), $this->secretResolver->resolve($connection))
                    : '';
            } catch (\Throwable) {
                // Without resolved secrets the message cannot be redacted safely, so show none.
                $reason = '';
            }
        };

        if (function_exists('add_action')) {
            add_action('sympress_mailer_delivery_failed', $capture, 10, 4);
        }

        try {
            $result = $this->send($to);
        } finally {
            if (function_exists('remove_action')) {
                remove_action('sympress_mailer_delivery_failed', $capture, 10);
            }
        }

        // An extension may queue a failed attempt for a retry: accepted, yet nothing was delivered.
        return [$result, !$result->accepted || $result->status === 'queued' ? $reason : ''];
    }
}
