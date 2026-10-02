<?php

declare(strict_types=1);

namespace SymPress\Mailer\Message;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Config\MailerSettings;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

final readonly class SymfonyEmailFactory
{
    public function __construct(
        private EmailBodyProcessorInterface $bodyProcessor,
        private AttachmentPolicyInterface $attachmentPolicy,
    ) {
    }

    public function create(WordPressMail $mail, ConnectionConfig $connection, MailerSettings $settings, string $logId): Email
    {
        $email = new Email();
        $email->subject($mail->subject);

        foreach ($mail->to as $address) {
            $email->addTo($address);
        }

        foreach ($mail->cc as $address) {
            $email->addCc($address);
        }

        foreach ($mail->bcc as $address) {
            $email->addBcc($address);
        }

        foreach ($settings->forwardEmails as $address) {
            $email->addBcc($address);
        }

        foreach ($mail->replyTo as $address) {
            $email->addReplyTo($address);
        }

        $from = $this->from($mail, $connection);
        $defaultHost = function_exists('network_home_url') ? (string) wp_parse_url(network_home_url(), PHP_URL_HOST) : 'localhost';
        $address = Address::create($from !== '' ? $from : 'wordpress@' . preg_replace('/^www\./', '', $defaultHost));
        $fromEmail = $address->getAddress();
        $fromName = $address->getName() !== '' ? $address->getName() : 'WordPress';
        if (function_exists('apply_filters')) {
            $fromEmail = (string) apply_filters('wp_mail_from', $fromEmail);
            $fromName = (string) apply_filters('wp_mail_from_name', $fromName);
        }
        $from = new Address($fromEmail, $fromName);

        if ($from->getAddress() !== '') {
            $email->from($from);
        }

        if ($connection->returnPath && $connection->fromEmail !== '') {
            $email->returnPath($connection->fromEmail);
        }

        $body = $mail->message;
        $contentType = $mail->contentType ?? 'text/plain';
        $charset = $mail->charset ?? (function_exists('get_bloginfo') ? get_bloginfo('charset') : 'UTF-8');
        if (function_exists('apply_filters')) {
            $contentType = (string) apply_filters('wp_mail_content_type', $contentType);
            $charset = (string) apply_filters('wp_mail_charset', $charset);
        }
        $isHtml = str_contains(strtolower($contentType), 'html');

        if ($isHtml) {
            $body = $this->bodyProcessor->process(
                $body,
                $mail,
                $connection,
                $settings,
                $logId,
            );
            $email->html($body, $charset);
            $email->text($this->textFallback($body), $charset);
        } else {
            $email->text($body, $charset);
        }

        foreach ($mail->headers as $name => $values) {
            if ($this->managedHeader($name)) {
                continue;
            }

            foreach ($values as $value) {
                $email->getHeaders()->addTextHeader($name, $value);
            }
        }

        foreach ($mail->attachments as $attachment) {
            if (!$this->attachmentPolicy->allowed($attachment)) {
                $this->reportBlockedAttachment($attachment, $mail, $connection, $logId);
                continue;
            }

            $email->attachFromPath($attachment);
        }

        $embedIds = [];
        foreach ($mail->embeds as $cid => $path) {
            $args = ['path' => $path, 'cid' => (string) $cid, 'name' => basename($path), 'type' => '', 'disposition' => 'inline', 'encoding' => 'base64'];
            if (function_exists('apply_filters')) {
                $args = apply_filters('wp_mail_embed_args', $args);
            }
            if (!is_array($args) || !is_string($args['path'] ?? null) || !$this->attachmentPolicy->allowed($args['path'])) {
                continue;
            }
            $part = DataPart::fromPath($args['path'], is_string($args['name'] ?? null) ? $args['name'] : null, !empty($args['type']) && is_string($args['type']) ? $args['type'] : null)->asInline();
            $nativeCid = is_string($args['cid'] ?? null) ? $args['cid'] : (string) $cid;
            $embedIds['cid:' . $nativeCid] = 'cid:' . $part->getContentId();
            $email->addPart($part);
        }

        $html = $email->getHtmlBody();
        if ($embedIds !== [] && is_string($html)) {
            $email->html(strtr($html, $embedIds), $email->getHtmlCharset() ?? $charset);
        }

        $email->getHeaders()->addTextHeader('X-SymPress-Mailer-Log-ID', $logId);

        return $email;
    }

    private function reportBlockedAttachment(string $attachment, WordPressMail $mail, ConnectionConfig $connection, string $logId): void
    {
        if (!function_exists('do_action')) {
            return;
        }

        $reason = 'Attachment was blocked by policy.';

        if (method_exists($this->attachmentPolicy, 'rejectionReason')) {
            $policyReason = $this->attachmentPolicy->rejectionReason($attachment);

            if (is_string($policyReason) && $policyReason !== '') {
                $reason = $policyReason;
            }
        }

        do_action('sympress_mailer_attachment_blocked', $logId, $attachment, $reason, $mail, $connection);
    }

    private function from(WordPressMail $mail, ConnectionConfig $connection): string
    {
        if (!$connection->forceFrom && $mail->from !== null && $mail->from !== '') {
            if ($connection->forceFromName && $connection->fromName !== '') {
                return sprintf('%s <%s>', $connection->fromName, $this->addressOnly($mail->from));
            }

            return $mail->from;
        }

        if ($connection->fromEmail === '') {
            return $mail->from ?? '';
        }

        if ($connection->fromName === '') {
            return $connection->fromEmail;
        }

        return sprintf('%s <%s>', $connection->fromName, $connection->fromEmail);
    }

    private function addressOnly(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $matches) === 1) {
            return trim($matches[1]);
        }

        return trim($from);
    }

    private function textFallback(string $html): string
    {
        $text = function_exists('wp_strip_all_tags')
            ? wp_strip_all_tags($html)
            // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- WordPress-free mail creation fallback.
            : strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", is_string($text) ? $text : '');

        return trim(is_string($text) ? $text : '');
    }

    private function managedHeader(string $name): bool
    {
        return in_array(
            strtolower($name),
            [
                'to',
                'subject',
                'message-id',
                'from',
                'cc',
                'bcc',
                'reply-to',
                'content-type',
                'mime-version',
            ],
            true,
        );
    }
}
