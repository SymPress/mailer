<?php

declare(strict_types=1);

namespace SymPress\Mailer\Support;

final class MailerRuntimeGuard
{
    private static bool $interceptionDisabled = false;

    public static function isInterceptionDisabled(): bool
    {
        return self::$interceptionDisabled;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function withoutInterception(callable $callback): mixed
    {
        $previous = self::$interceptionDisabled;
        self::$interceptionDisabled = true;

        try {
            return $callback();
        } finally {
            self::$interceptionDisabled = $previous;
        }
    }
}
