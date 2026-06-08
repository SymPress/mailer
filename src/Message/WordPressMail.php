<?php

declare(strict_types=1);

namespace SymPress\Mailer\Message;

final readonly class WordPressMail
{
    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     * @param list<string> $replyTo
     * @param array<string, list<string>> $headers
     * @param list<string> $attachments
     */
    public function __construct(
        public array $to,
        public string $subject,
        public string $message,
        public array $headers = [],
        public array $attachments = [],
        public ?string $from = null,
        public array $cc = [],
        public array $bcc = [],
        public array $replyTo = [],
        public ?string $contentType = null,
        public ?string $charset = null,
        public string $source = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'subject' => $this->subject,
            'message' => $this->message,
            'headers' => $this->headers,
            'attachments' => $this->attachments,
            'from' => $this->from,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'reply_to' => $this->replyTo,
            'content_type' => $this->contentType,
            'charset' => $this->charset,
            'source' => $this->source,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            to: self::list($data['to'] ?? []),
            subject: self::string($data['subject'] ?? ''),
            message: self::string($data['message'] ?? ''),
            headers: self::headers($data['headers'] ?? []),
            attachments: self::list($data['attachments'] ?? []),
            from: self::nullableString($data['from'] ?? null),
            cc: self::list($data['cc'] ?? []),
            bcc: self::list($data['bcc'] ?? []),
            replyTo: self::list($data['reply_to'] ?? $data['replyTo'] ?? []),
            contentType: self::nullableString($data['content_type'] ?? $data['contentType'] ?? null),
            charset: self::nullableString($data['charset'] ?? null),
            source: self::string($data['source'] ?? ''),
        );
    }

    /**
     * @param mixed $value
     */
    private static function string($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param mixed $value
     */
    private static function nullableString($value): ?string
    {
        $value = self::string($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function list($value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $item) {
            $string = self::string($item);

            if ($string !== '') {
                $list[] = $string;
            }
        }

        return $list;
    }

    /**
     * @param mixed $value
     * @return array<string, list<string>>
     */
    private static function headers($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $headers = [];

        foreach ($value as $name => $items) {
            if (!is_string($name)) {
                continue;
            }

            $headers[$name] = self::list($items);
        }

        return $headers;
    }
}
