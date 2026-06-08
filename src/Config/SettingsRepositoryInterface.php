<?php

declare(strict_types=1);

namespace SymPress\Mailer\Config;

interface SettingsRepositoryInterface
{
    public function get(): MailerSettings;

    public function save(MailerSettings $settings): void;
}
