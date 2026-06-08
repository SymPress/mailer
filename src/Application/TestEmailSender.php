<?php

declare(strict_types=1);

namespace SymPress\Mailer\Application;

use SymPress\Mailer\Message\WordPressMail;
use SymPress\Mailer\Value\SendResult;

final readonly class TestEmailSender
{
    public function __construct(
        private MailerInterface $mailer,
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
}
