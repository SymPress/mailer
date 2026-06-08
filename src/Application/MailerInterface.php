<?php

declare(strict_types=1);

namespace SymPress\Mailer\Application;

use SymPress\Mailer\Message\WordPressMail;
use SymPress\Mailer\Value\SendResult;

interface MailerInterface
{
    public function send(WordPressMail $mail): SendResult;
}
