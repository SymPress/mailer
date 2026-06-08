<?php

declare(strict_types=1);

namespace SymPress\Mailer\Config;

use SymPress\Mailer\Support\WordPressArray;

final readonly class RateLimitConfig
{
    public function __construct(
        public bool $enabled = false,
        public int $limit = 0,
        public string $interval = 'minute',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $interval = WordPressArray::string($data['interval'] ?? 'minute');

        if (!in_array($interval, ['minute', 'hour', 'day', 'week', 'month'], true)) {
            $interval = 'minute';
        }

        return new self(
            enabled: WordPressArray::bool($data['enabled'] ?? false),
            limit: max(0, WordPressArray::int($data['limit'] ?? 0)),
            interval: $interval,
        );
    }

    /**
     * @return array<string, scalar>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'limit' => $this->limit,
            'interval' => $this->interval,
        ];
    }

    public function windowSeconds(): int
    {
        return match ($this->interval) {
            'month' => 30 * 86400,
            'week' => 7 * 86400,
            'day' => 86400,
            'hour' => 3600,
            default => 60,
        };
    }

    public function bucketFormat(): string
    {
        return match ($this->interval) {
            'month' => 'Ym',
            'week' => 'oW',
            'day' => 'Ymd',
            'hour' => 'YmdH',
            default => 'YmdHi',
        };
    }
}
