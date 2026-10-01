<?php

declare(strict_types=1);

namespace SymPress\Mailer\Secret;

final class SecretFields
{
    public const array CONNECTION = ['dsn', 'username', 'password', 'api_key', 'api_secret', 'domain', 'tenant_id'];
    public const array ALERT = ['slack_webhook', 'discord_webhook', 'teams_webhook', 'twilio_auth_token', 'whatsapp_access_token'];

    public static function isSecret(string $name): bool
    {
        return in_array($name, [...self::CONNECTION, ...self::ALERT, 'alert_webhooks', 'connections_json'], true);
    }

    /**
     * @param array<string, mixed> $submitted
     * @param array<string, mixed> $previous
     * @return array<string, mixed>
     */
    public static function preserve(array $submitted, array $previous): array
    {
        $clear = is_array($submitted['_clear_secrets'] ?? null) ? $submitted['_clear_secrets'] : [];
        foreach ([...self::CONNECTION, ...self::ALERT, 'alert_webhooks'] as $field) {
            if (!empty($clear[$field])) {
                $submitted[$field] = $field === 'alert_webhooks' ? [] : '';
            } elseif (!isset($submitted[$field]) || $submitted[$field] === '' || $submitted[$field] === []) {
                $submitted[$field] = $previous[$field] ?? ($field === 'alert_webhooks' ? [] : '');
            }
        }
        unset($submitted['_clear_secrets']);
        return $submitted;
    }

    public static function fieldName(string $name): string
    {
        preg_match('/(?:^|\[)([^\[\]]+)\]?$/', $name, $parts);
        return $parts[1] ?? $name;
    }

    public static function clearName(string $name): string
    {
        $field = self::fieldName($name);
        $prefix = str_contains($name, '[') ? substr($name, 0, (int) strrpos($name, '[')) : '';
        return $prefix === '' ? '_clear_secrets[' . $field . ']' : $prefix . '[_clear_secrets][' . $field . ']';
    }

    /**
     * @param array<string, mixed> $connection
     * @return array<string, mixed>
     */
    public static function hidden(array $connection): array
    {
        foreach (self::CONNECTION as $field) {
            $connection[$field] = '';
        }
        return $connection;
    }
}
