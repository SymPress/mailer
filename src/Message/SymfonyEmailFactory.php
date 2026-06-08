<?php

declare(strict_types=1);

namespace SymPress\Mailer\Message;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Config\MailerSettings;
use Symfony\Component\Mime\Email;

final readonly class SymfonyEmailFactory
{
    public function __construct(
        private EmailBodyProcessorInterface $bodyProcessor,
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

        if ($from !== '') {
            $email->from($from);
        }

        if ($connection->returnPath && $connection->fromEmail !== '') {
            $email->returnPath($connection->fromEmail);
        }

        $body = $mail->message;
        $isHtml = $this->isHtml($mail, $body);

        if ($isHtml) {
            $body = $this->bodyProcessor->process(
                $body,
                $mail,
                $connection,
                $settings,
                $logId,
            );
            $email->html($body);
            $email->text($this->textFallback($body));
        } else {
            $email->text($body);
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
            if (is_readable($attachment)) {
                $email->attachFromPath($attachment);
            }
        }

        $email->getHeaders()->addTextHeader('X-SymPress-Mailer-Log-ID', $logId);

        if ($mail->source !== '') {
            $email->getHeaders()->addTextHeader('X-SymPress-Mailer-Source', $mail->source);
        }

        return $email;
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

    private function isHtml(WordPressMail $mail, string $body): bool
    {
        if ($mail->contentType !== null && str_contains(strtolower($mail->contentType), 'html')) {
            return true;
        }

        return stripos($body, '<html') !== false
            || stripos($body, '<body') !== false
            || stripos($body, '<p>') !== false
            || stripos($body, '<br') !== false;
    }

    private function textFallback(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
