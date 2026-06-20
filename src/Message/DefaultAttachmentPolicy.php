<?php

declare(strict_types=1);

namespace SymPress\Mailer\Message;

final class DefaultAttachmentPolicy implements AttachmentPolicyInterface
{
    #[\Override]
    public function allowed(string $path): bool
    {
        return $this->rejectionReason($path) === null;
    }

    public function rejectionReason(string $path): ?string
    {
        $realPath = realpath($path);

        if ($realPath === false) {
            return 'Attachment file does not exist or is no longer accessible.';
        }

        if (!is_file($realPath)) {
            return 'Attachment path is not a regular file.';
        }

        if (!is_readable($realPath)) {
            return 'Attachment file is not readable.';
        }

        if (basename($realPath) === 'wp-config.php') {
            return 'Sensitive WordPress config files are blocked by attachment policy.';
        }

        if (basename($realPath) === '.htaccess') {
            return 'Sensitive web server config files are blocked by attachment policy.';
        }

        foreach ($this->blockedPaths() as $blockedPath) {
            $blocked = realpath($blockedPath) ?: $blockedPath;

            if ($blocked !== '' && str_starts_with($realPath, rtrim($blocked, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                return 'Sensitive system paths are blocked by attachment policy.';
            }

            if ($blocked !== '' && $realPath === $blocked) {
                return 'Sensitive system paths are blocked by attachment policy.';
            }
        }

        return null;
    }

    /** @return list<string> */
    private function blockedPaths(): array
    {
        $paths = ['/etc', '/proc', '/sys', '/dev', '/root'];

        if (defined('ABSPATH')) {
            $paths[] = ABSPATH . 'wp-config.php';
            $paths[] = dirname((string) ABSPATH) . '/wp-config.php';
            $paths[] = ABSPATH . '.htaccess';
        }

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('sympress_mailer_blocked_attachment_paths', $paths);

            if (is_array($filtered)) {
                $paths = array_values(array_filter($filtered, 'is_string'));
            }
        }

        return $paths;
    }
}
