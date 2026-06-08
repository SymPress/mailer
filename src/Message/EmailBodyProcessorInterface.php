<?php

declare(strict_types=1);

namespace SymPress\Mailer\Message;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Config\MailerSettings;

interface EmailBodyProcessorInterface
{
    public function process(
        string $body,
        WordPressMail $mail,
        ConnectionConfig $connection,
        MailerSettings $settings,
        string $messageId,
    ): string;
}
