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

final class SymfonyEmailFactoryTest extends TestCase
{
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

    private function factory(): SymfonyEmailFactory
    {
        return new SymfonyEmailFactory(new NullEmailBodyProcessor(), new DefaultAttachmentPolicy());
    }
}
