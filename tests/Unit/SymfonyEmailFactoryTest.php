<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Message\DefaultAttachmentPolicy;
use SymPress\Mailer\Message\NullEmailBodyProcessor;
use SymPress\Mailer\Message\SymfonyEmailFactory;
use SymPress\Mailer\Message\WordPressMail;
use Symfony\Component\Mailer\Transport\NullTransport;

final class SymfonyEmailFactoryTest extends TestCase
{
    public function testCallerCannotSpoofOrDuplicateTheManagedLogId(): void
    {
        $email = $this->factory()->create(
            new WordPressMail(
                to: ['reader@example.test'],
                subject: 'Managed header',
                message: 'Body',
                headers: ['x-sympress-mailer-log-id' => ['spoofed'], 'X-SymPress-Mailer-Log-ID' => ['duplicate']],
            ),
            new ConnectionConfig(id: 'primary', fromEmail: 'team@example.test'),
            new MailerSettings(),
            'actual-log-id',
        );
        self::assertCount(1, iterator_to_array($email->getHeaders()->all('X-SymPress-Mailer-Log-ID')));
        self::assertSame('actual-log-id', $email->getHeaders()->get('X-SymPress-Mailer-Log-ID')?->getBodyAsString());
    }

    public function testReportsBlockedAttachmentWithoutAddingItToEmail(): void
    {
        $GLOBALS['sympress_mailer_test_actions'] = [];
        $missing = sys_get_temp_dir() . '/spm-missing-attachment-' . bin2hex(random_bytes(4));

        $email = $this->factory()->create(
            new WordPressMail(
                to: ['reader@example.test'],
                subject: 'Attachment',
                message: 'Body',
                attachments: [$missing],
            ),
            new ConnectionConfig(id: 'primary', fromEmail: 'team@example.test'),
            new MailerSettings(),
            'log-123',
        );

        self::assertCount(0, $email->getAttachments());
        self::assertCount(1, $GLOBALS['sympress_mailer_test_actions']);
        self::assertSame('sympress_mailer_attachment_blocked', $GLOBALS['sympress_mailer_test_actions'][0]['hook']);
        self::assertSame('log-123', $GLOBALS['sympress_mailer_test_actions'][0]['args'][0]);
        self::assertSame($missing, $GLOBALS['sympress_mailer_test_actions'][0]['args'][1]);
        self::assertSame('Attachment file does not exist or is no longer accessible.', $GLOBALS['sympress_mailer_test_actions'][0]['args'][2]);
    }

    public function testAllowedAttachmentIsAddedWithoutPolicyAction(): void
    {
        $GLOBALS['sympress_mailer_test_actions'] = [];
        $path = tempnam(sys_get_temp_dir(), 'spm-attachment-');
        self::assertIsString($path);
        file_put_contents($path, 'attachment');

        try {
            $email = $this->factory()->create(
                new WordPressMail(
                    to: ['reader@example.test'],
                    subject: 'Attachment',
                    message: 'Body',
                    attachments: [$path],
                ),
                new ConnectionConfig(id: 'primary', fromEmail: 'team@example.test'),
                new MailerSettings(),
                'log-123',
            );

            self::assertCount(1, $email->getAttachments());
            self::assertSame([], $GLOBALS['sympress_mailer_test_actions']);
        } finally {
            @unlink($path);
        }
    }

    public function testAppliesSenderDateAndReturnPathHeadersThroughTypedApi(): void
    {
        $email = $this->factory()->create(
            new WordPressMail(
                to: ['reader@example.test'],
                subject: 'Typed headers',
                message: 'Body',
                headers: [
                    'Sender' => ['first@example.test', 'bot@example.test'],
                    // 1 Jan 2026 is a Thursday: a wrong weekday must not move the date.
                    'Date' => ['Mon, 01 Jan 2026 00:00:00 +0000'],
                    'Return-Path' => ['bounce@example.test'],
                ],
            ),
            new ConnectionConfig(id: 'primary', fromEmail: 'team@example.test'),
            new MailerSettings(),
            'log-123',
        );

        self::assertSame('bot@example.test', $email->getSender()?->getAddress());
        self::assertSame('2026-01-01T00:00:00+00:00', $email->getDate()?->format(DATE_ATOM));
        self::assertSame('bounce@example.test', $email->getReturnPath()?->getAddress());
        self::assertStringContainsString('Sender: bot@example.test', $email->toString());
    }

    public function testSkipsInvalidTypedHeaderValuesAndStillBuildsTheMail(): void
    {
        $email = $this->factory()->create(
            new WordPressMail(
                to: ['reader@example.test'],
                subject: 'Invalid typed headers',
                message: 'Body',
                headers: [
                    'Sender' => ['not an address'],
                    'Date' => ['not a date'],
                    'Return-Path' => ['not an address'],
                ],
            ),
            new ConnectionConfig(id: 'primary', fromEmail: 'team@example.test'),
            new MailerSettings(),
            'log-123',
        );

        self::assertNull($email->getSender());
        self::assertNull($email->getDate());
        self::assertNull($email->getReturnPath());
        self::assertStringContainsString('Subject: Invalid typed headers', $email->toString());
    }

    public function testSkipsMalformedHeaderNamesButKeepsValidCustomHeaders(): void
    {
        $email = $this->factory()->create(
            new WordPressMail(
                to: ['reader@example.test'],
                subject: 'Malformed headers',
                message: 'Body',
                headers: ['' => ['value'], 'Bad Name' => ['value'], '123' => ['numeric'], 'X-Custom' => ['kept']],
            ),
            new ConnectionConfig(id: 'primary', fromEmail: 'team@example.test'),
            new MailerSettings(),
            'log-123',
        );

        self::assertFalse($email->getHeaders()->has('Bad Name'));
        self::assertSame('numeric', $email->getHeaders()->get('123')?->getBodyAsString());
        self::assertSame('kept', $email->getHeaders()->get('X-Custom')?->getBodyAsString());
        self::assertStringContainsString('X-Custom: kept', $email->toString());
    }

    public function testConfiguredReturnPathBeatsReturnPathHeader(): void
    {
        $email = $this->factory()->create(
            new WordPressMail(
                to: ['reader@example.test'],
                subject: 'Return path',
                message: 'Body',
                headers: ['Return-Path' => ['bounce@example.test']],
            ),
            new ConnectionConfig(id: 'primary', fromEmail: 'team@example.test', returnPath: true),
            new MailerSettings(),
            'log-123',
        );

        self::assertSame('team@example.test', $email->getReturnPath()?->getAddress());
    }

    public function testSkipsInvalidRecipientsAndReportsEachOne(): void
    {
        $GLOBALS['sympress_mailer_test_actions'] = [];
        $mail = new WordPressMail(
            to: ['reader@example.test', 'not-an-address'],
            subject: 'Recipients',
            message: 'Body',
            cc: ['broken@', 'copy@example.test'],
            bcc: ['bad bcc'],
            replyTo: ['reply@', 'answer@example.test'],
        );

        $email = $this->factory()->create(
            $mail,
            new ConnectionConfig(id: 'primary', fromEmail: 'team@example.test'),
            new MailerSettings(forwardEmails: ['archive@example.test', 'forward@']),
            'log-123',
        );

        self::assertSame(['reader@example.test'], array_map(static fn ($a) => $a->getAddress(), $email->getTo()));
        self::assertSame(['copy@example.test'], array_map(static fn ($a) => $a->getAddress(), $email->getCc()));
        self::assertSame(['archive@example.test'], array_map(static fn ($a) => $a->getAddress(), $email->getBcc()));
        self::assertSame(['answer@example.test'], array_map(static fn ($a) => $a->getAddress(), $email->getReplyTo()));
        self::assertSame(
            [
                ['sympress_mailer_recipient_skipped', ['log-123', 'to', 'not-an-address', $mail]],
                ['sympress_mailer_recipient_skipped', ['log-123', 'cc', 'broken@', $mail]],
                ['sympress_mailer_recipient_skipped', ['log-123', 'bcc', 'bad bcc', $mail]],
                ['sympress_mailer_recipient_skipped', ['log-123', 'bcc', 'forward@', $mail]],
                ['sympress_mailer_recipient_skipped', ['log-123', 'reply-to', 'reply@', $mail]],
            ],
            array_map(static fn (array $action) => [$action['hook'], $action['args']], $GLOBALS['sympress_mailer_test_actions']),
        );
    }

    public function testMailWithoutAnyValidRecipientStillFailsToSend(): void
    {
        $email = $this->factory()->create(
            new WordPressMail(to: ['not-an-address'], subject: 'Recipients', message: 'Body', cc: ['broken@']),
            new ConnectionConfig(id: 'primary', fromEmail: 'team@example.test'),
            new MailerSettings(),
            'log-123',
        );

        self::assertSame([], $email->getTo());
        self::assertSame([], $email->getCc());
        self::assertSame([], $email->getBcc());

        $this->expectException(\LogicException::class);
        (new NullTransport())->send($email);
    }

    private function factory(): SymfonyEmailFactory
    {
        return new SymfonyEmailFactory(new NullEmailBodyProcessor(), new DefaultAttachmentPolicy());
    }
}
