<?php

declare(strict_types=1);

namespace SymPress\Mailer\Application;

use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Message\SymfonyEmailFactory;
use SymPress\Mailer\Message\WordPressMail;
use SymPress\Mailer\Transport\SymfonyMailerFactory;
use SymPress\Mailer\Value\SendResult;

final readonly class MailerService implements MailerInterface
{
    public function __construct(
        private SettingsRepositoryInterface $settingsRepository,
        private SymfonyEmailFactory $emailFactory,
        private SymfonyMailerFactory $mailerFactory,
    ) {
    }

    #[\Override]
    public function send(WordPressMail $mail): SendResult
    {
        $settings = $this->settingsRepository->get();

        if ($settings->doNotSend) {
            return SendResult::suppressed('do_not_send');
        }

        $connection = $settings->defaultConnection();
        $logId = bin2hex(random_bytes(16));

        try {
            $email = $this->emailFactory->create($mail, $connection, $settings, $logId);
            $this->mailerFactory->create($connection)->send($email);

            return SendResult::sent($logId, $connection->id);
        } catch (\Throwable $throwable) {
            return SendResult::failed($logId, $throwable->getMessage());
        }
    }
}
