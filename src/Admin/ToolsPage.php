<?php

declare(strict_types=1);

namespace SymPress\Mailer\Admin;

use SymPress\Mailer\Application\TestEmailSender;
use SymPress\Mailer\Config\DeliverySettings;
use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Support\WordPressArray;
use SymPress\Mailer\Validation\SmtpConnectionProbe;

/** The Tools screen: test email and SMTP connection check. */
final readonly class ToolsPage
{
    use AdminScreenHelpers;

    public function __construct(
        private SettingsRepositoryInterface $settingsRepository,
        private TestEmailSender $testEmailSender,
        private SmtpConnectionProbe $connectionProbe,
    ) {
    }

    public function renderTest(): void
    {
        if ($this->proPluginActive()) {
            return;
        }

        $this->assertCapability();
        $settings = $this->settingsRepository->get();

        $this->chromeStart(__('Tools', 'sympress-mailer'), $settings);

        $status = WordPressArray::string(WordPressArray::get()['sympress_mailer_test'] ?? '');
        if ($status === 'sent') {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Test email sent.', 'sympress-mailer') . '</p></div>';
        } elseif ($status !== '') {
            $reason = function_exists('get_transient') ? get_transient($this->testFailureKey()) : false;
            $message = __('Test email failed.', 'sympress-mailer');

            if (is_string($reason) && $reason !== '') {
                delete_transient($this->testFailureKey());
                /* translators: %s: the reason the test email failed, e.g. a server error message. */
                $message = sprintf(__('Test email failed: %s', 'sympress-mailer'), $reason);
            }

            echo '<div class="notice notice-error inline"><p>' . $this->esc($message) . '</p></div>';
        }

        $this->connectionCheckNotice();

        echo '<section class="spm-section">';
        echo '<h2>' . esc_html__('Email Test', 'sympress-mailer') . '</h2>';
        echo '<form method="post" action="' . $this->attr($this->adminPostUrl()) . '">';
        echo '<input type="hidden" name="action" value="sympress_mailer_send_test">';
        $this->nonce('sympress_mailer_send_test');
        $this->input('to', __('Recipient', 'sympress-mailer'), $this->defaultRecipient(), 'email');
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Send Test Email', 'sympress-mailer') . '</button></p>';
        echo '</form>';
        echo '</section>';

        echo '<section class="spm-section">';
        echo '<h2>' . esc_html__('Connection Check', 'sympress-mailer') . '</h2>';
        echo '<p class="description">' . esc_html__('Connects to the SMTP server and logs in without sending an email.', 'sympress-mailer') . '</p>';
        echo '<form method="post" action="' . $this->attr($this->adminPostUrl()) . '">';
        echo '<input type="hidden" name="action" value="sympress_mailer_check_connection">';
        $this->nonce('sympress_mailer_check_connection');
        echo '<p><button type="submit" class="button">' . esc_html__('Check Connection', 'sympress-mailer') . '</button></p>';
        echo '</form>';
        echo '</section>';
        $this->chromeEnd();
    }

    public function sendTest(): void
    {
        if ($this->proPluginActive()) {
            return;
        }

        $this->assertCapability();
        $this->checkNonce('sympress_mailer_send_test');

        $to = WordPressArray::string(WordPressArray::post()['to'] ?? '');
        [$result, $reason] = $to !== '' ? $this->testEmailSender->sendWithFailureReason($to) : [null, ''];
        $status = $result?->accepted === true ? 'sent' : 'failed';

        // A site admin must not see errors from inherited network settings they cannot view.
        if ($reason !== '' && DeliverySettings::read($this->settingsRepository)->toArray() !== $this->settingsRepository->get()->toArray()) {
            $reason = '';
        }

        if (function_exists('set_transient')) {
            if ($status === 'failed' && $reason !== '') {
                set_transient($this->testFailureKey(), $reason, 60);
            } else {
                delete_transient($this->testFailureKey());
            }
        }

        $this->redirect(self::SLUG . '-tools', ['sympress_mailer_test' => $status]);
    }

    public function checkConnection(): void
    {
        if ($this->proPluginActive()) {
            return;
        }

        $this->assertCapability();
        $this->checkNonce('sympress_mailer_check_connection');

        $health = $this->connectionProbe->probe($this->settingsRepository->get()->defaultConnection());

        if (function_exists('set_transient')) {
            set_transient($this->connectionCheckKey(), [
                'healthy' => $health->healthy,
                'checked' => ($health->context['checked'] ?? true) !== false,
                'message' => $health->message,
            ], 60);
        }

        $this->redirect(self::SLUG . '-tools', ['sympress_mailer_check' => '1']);
    }

    private function testFailureKey(): string
    {
        return 'sympress_mailer_test_failure_' . (function_exists('get_current_user_id') ? get_current_user_id() : 0);
    }

    private function connectionCheckKey(): string
    {
        return 'sympress_mailer_connection_check_' . (function_exists('get_current_user_id') ? get_current_user_id() : 0);
    }

    private function connectionCheckNotice(): void
    {
        if (WordPressArray::string(WordPressArray::get()['sympress_mailer_check'] ?? '') === '' || !function_exists('get_transient')) {
            return;
        }

        $check = get_transient($this->connectionCheckKey());

        if (!is_array($check)) {
            return;
        }

        delete_transient($this->connectionCheckKey());
        $message = WordPressArray::string($check['message'] ?? '');

        if (($check['checked'] ?? true) === false) {
            echo '<div class="notice notice-info inline"><p>' . esc_html__('This connection type cannot be checked without sending. Send a test email instead.', 'sympress-mailer') . '</p></div>';
        } elseif (($check['healthy'] ?? false) === true) {
            /* translators: %s: the result reported by the mail server check. */
            echo '<div class="notice notice-success inline"><p>' . $this->esc(sprintf(__('Connection check passed: %s', 'sympress-mailer'), $message)) . '</p></div>';
        } else {
            /* translators: %s: the reason the connection check failed, e.g. a server error message. */
            echo '<div class="notice notice-error inline"><p>' . $this->esc(sprintf(__('Connection check failed: %s', 'sympress-mailer'), $message)) . '</p></div>';
        }
    }

    private function defaultRecipient(): string
    {
        return function_exists('get_option') ? (string) get_option('admin_email', '') : '';
    }
}
