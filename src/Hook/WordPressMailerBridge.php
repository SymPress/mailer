<?php

declare(strict_types=1);

namespace SymPress\Mailer\Hook;

use SymPress\Mailer\Application\MailerInterface;
use SymPress\Mailer\Config\DeliverySettings;
use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Message\WordPressMailParser;
use SymPress\Mailer\Support\MailerRuntimeGuard;
use SymPress\Mailer\Value\SendResult;

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

        try {
            $settings = DeliverySettings::read($this->settingsRepository);
        } catch (\Throwable) {
            if (function_exists('do_action')) {
                do_action('wp_mail_failed', new \WP_Error('wp_mail_failed', 'Mailer credentials are unavailable.', $atts));
            }
            return false;
        }

        if (!$settings->enabled) {
            return null;
        }

        try {
            $mail = $this->parser->parse($atts);
            $result = $settings->doNotSend
                ? SendResult::suppressed('do_not_send')
                : $this->mailer->send($mail);
        } catch (\Throwable) {
            $result = SendResult::failed('', 'Mailer configuration or delivery failed.');
        }
        // Match WordPress' action payload after wp_mail filters and list normalization.
        $data = array_intersect_key($atts, array_flip(['to', 'subject', 'message', 'headers', 'attachments', 'embeds']));
        $data['to'] = $mail->to ?? [];
        $data['attachments'] = $mail->attachments ?? [];
        $data['embeds'] = $mail->embeds ?? [];
        $data['headers'] = [];
        foreach ($mail->headers ?? [] as $name => $values) {
            if (in_array(strtolower($name), ['from', 'cc', 'bcc', 'reply-to', 'content-type'], true)) {
                continue;
            }
            $data['headers'][$name] = end($values);
        }
        if (function_exists('do_action')) {
            if ($result->accepted) {
                do_action('wp_mail_succeeded', $data);
            } else {
                do_action('wp_mail_failed', new \WP_Error('wp_mail_failed', $result->error ?? 'Mail delivery failed.', $data));
            }
        }
        return $result->accepted;
    }
}
