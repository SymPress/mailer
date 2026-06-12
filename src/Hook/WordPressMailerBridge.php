<?php

declare(strict_types=1);

namespace SymPress\Mailer\Hook;

use SymPress\Mailer\Application\MailerInterface;
use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Message\WordPressMailParser;
use SymPress\Mailer\Support\MailerRuntimeGuard;

final readonly class WordPressMailerBridge
{
    public function __construct(
        private SettingsRepositoryInterface $settingsRepository,
        private WordPressMailParser $parser,
        private MailerInterface $mailer,
    ) {
    }

    /** @param array<string, mixed> $atts */
    public function send(?bool $return, array $atts): ?bool
    {
        if ($return !== null || MailerRuntimeGuard::isInterceptionDisabled()) {
            return $return;
        }

        $settings = $this->settingsRepository->get();

        if (!$settings->enabled) {
            return null;
        }

        if ($settings->doNotSend) {
            return true;
        }

        return $this->mailer->send($this->parser->parse($atts))->accepted;
    }
}
