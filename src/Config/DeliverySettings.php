<?php

declare(strict_types=1);

namespace SymPress\Mailer\Config;

/** Reads the delivery view while preserving existing custom repositories. */
final class DeliverySettings
{
    public static function read(SettingsRepositoryInterface $repository): MailerSettings
    {
        return $repository instanceof DeliverySettingsRepositoryInterface
            ? $repository->getForDelivery()
            : $repository->get();
    }
}
