<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Message\DefaultAttachmentPolicy;

final class AttachmentPolicyTest extends TestCase
{
    public function testAllowsReadableRegularFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'spm-attachment-');
        self::assertIsString($path);
        file_put_contents($path, 'attachment');

        try {
            self::assertTrue((new DefaultAttachmentPolicy())->allowed($path));
        } finally {
            @unlink($path);
        }
    }

    public function testBlocksSensitiveSystemPath(): void
    {
        $policy = new DefaultAttachmentPolicy();

        self::assertFalse($policy->allowed('/etc/passwd'));
        self::assertSame('Sensitive system paths are blocked by attachment policy.', $policy->rejectionReason('/etc/passwd'));
    }

    public function testReportsMissingAttachmentReason(): void
    {
        $path = sys_get_temp_dir() . '/spm-missing-attachment-' . bin2hex(random_bytes(4));

        self::assertSame('Attachment file does not exist or is no longer accessible.', (new DefaultAttachmentPolicy())->rejectionReason($path));
    }

    public function testBlocksSensitiveWordPressConfigByBasename(): void
    {
        $dir = sys_get_temp_dir() . '/spm-attachment-policy-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $path = $dir . '/wp-config.php';
        file_put_contents($path, 'secret');

        try {
            $policy = new DefaultAttachmentPolicy();

            self::assertFalse($policy->allowed($path));
            self::assertSame('Sensitive WordPress config files are blocked by attachment policy.', $policy->rejectionReason($path));
        } finally {
            @unlink($path);
            @rmdir($dir);
        }
    }
}
