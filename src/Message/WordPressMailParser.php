<?php

declare(strict_types=1);

namespace SymPress\Mailer\Message;

final class WordPressMailParser
{
    /**
     * @param array<string, mixed> $atts
     */
    public function parse(array $atts): WordPressMail
    {
        $headers = $this->parseHeaders($atts['headers'] ?? []);

        return new WordPressMail(
            to: $this->addresses($atts['to'] ?? []),
            subject: $this->scalar($atts['subject'] ?? ''),
            message: $this->scalar($atts['message'] ?? ''),
            headers: $headers['headers'],
            attachments: $this->attachments($atts['attachments'] ?? []),
            from: $headers['from'],
            cc: $headers['cc'],
            bcc: $headers['bcc'],
            replyTo: $headers['reply_to'],
            contentType: $headers['content_type'],
            charset: $headers['charset'],
            source: $this->sourceFromBacktrace(),
        );
    }

    /**
     * @param mixed $headers
     * @return array{
     *     headers: array<string, list<string>>,
     *     from: ?string,
     *     cc: list<string>,
     *     bcc: list<string>,
     *     reply_to: list<string>,
     *     content_type: ?string,
     *     charset: ?string
     * }
     */
    private function parseHeaders($headers): array
    {
        $parsed = [
            'headers' => [],
            'from' => null,
            'cc' => [],
            'bcc' => [],
            'reply_to' => [],
            'content_type' => null,
            'charset' => null,
        ];

        foreach ($this->headerLines($headers) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $normalized = strtolower($name);

            $parsed['headers'][$name] ??= [];
            $parsed['headers'][$name][] = $value;

            match ($normalized) {
                'from' => $parsed['from'] = $value,
                'cc' => $parsed['cc'] = [...$parsed['cc'], ...$this->addresses($value)],
                'bcc' => $parsed['bcc'] = [...$parsed['bcc'], ...$this->addresses($value)],
                'reply-to' => $parsed['reply_to'] = [...$parsed['reply_to'], ...$this->addresses($value)],
                'content-type' => $this->parseContentType($value, $parsed),
                default => null,
            };
        }

        return $parsed;
    }

    /**
     * @param string $value
     * @param array{
     *     headers: array<string, list<string>>,
     *     from: ?string,
     *     cc: list<string>,
     *     bcc: list<string>,
     *     reply_to: list<string>,
     *     content_type: ?string,
     *     charset: ?string
     * } $parsed
     */
    private function parseContentType(string $value, array &$parsed): null
    {
        $parts = array_map('trim', explode(';', $value));
        $parsed['content_type'] = strtolower($parts[0] ?? '');

        foreach ($parts as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }

            [$name, $partValue] = array_map('trim', explode('=', $part, 2));

            if (strtolower($name) === 'charset') {
                $parsed['charset'] = trim($partValue, '"\'');
            }
        }

        return null;
    }

    /**
     * @param mixed $headers
     * @return list<string>
     */
    private function headerLines($headers): array
    {
        if (is_string($headers)) {
            $headers = preg_split('/\r\n|\r|\n/', $headers) ?: [];
        }

        if (!is_array($headers)) {
            return [];
        }

        $lines = [];

        foreach ($headers as $key => $value) {
            if (is_string($key) && !is_int($key)) {
                $lines[] = sprintf('%s: %s', $key, $this->scalar($value));
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $nested) {
                    $line = $this->scalar($nested);

                    if ($line !== '') {
                        $lines[] = $line;
                    }
                }

                continue;
            }

            $line = $this->scalar($value);

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function addresses($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/,(?![^<]*>)/', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $addresses = [];

        foreach ($value as $address) {
            $address = $this->scalar($address);

            if ($address !== '') {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function attachments($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $attachments = [];

        foreach ($value as $attachment) {
            $attachment = $this->scalar($attachment);

            if ($attachment !== '') {
                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    /**
     * @param mixed $value
     */
    private function scalar($value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function sourceFromBacktrace(): string
    {
        $pluginDir = defined('WP_PLUGIN_DIR') ? (string) WP_PLUGIN_DIR : '';
        $muPluginDir = defined('WPMU_PLUGIN_DIR') ? (string) WPMU_PLUGIN_DIR : '';

        if ($pluginDir === '' && $muPluginDir === '') {
            return '';
        }

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25) as $frame) {
            $file = isset($frame['file']) && is_string($frame['file']) ? $frame['file'] : '';

            foreach ([$pluginDir, $muPluginDir] as $base) {
                if ($base === '' || !str_starts_with($file, rtrim($base, '/') . '/')) {
                    continue;
                }

                $relative = ltrim(substr($file, strlen(rtrim($base, '/'))), '/');
                $parts = explode('/', $relative);

                return $parts[0] ?? '';
            }
        }

        return '';
    }
}
