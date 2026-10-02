<?php

declare(strict_types=1);

namespace SymPress\Mailer\Config;

/** Optional runtime view; get() and save() retain their administrative scope. */
interface DeliverySettingsRepositoryInterface extends SettingsRepositoryInterface
{
    public function getForDelivery(): MailerSettings;
}
