<?php

declare(strict_types=1);

namespace SymPress\Mailer\Config;

use SymPress\Mailer\Support\WordPressArray;

final readonly class MailerSettings
{
    /**
     * @param array<string, ConnectionConfig> $connections
     * @param list<array<string, mixed>> $routingRules
     * @param list<string> $alertEmails
     * @param list<string> $alertWebhooks
     * @param list<string> $forwardEmails
     * @param list<string> $disabledNotifications
     */
    public function __construct(
        public bool $enabled = true,
        public string $defaultConnection = 'primary',
        public ?string $backupConnection = null,
        public array $connections = [],
        public bool $loggingEnabled = true,
        public bool $saveBody = true,
        public bool $saveAttachments = false,
        public bool $openTracking = false,
        public bool $clickTracking = false,
        public bool $weeklySummary = false,
        public bool $reportsEnabled = true,
        public bool $smartRoutingEnabled = true,
        public RateLimitConfig $rateLimit = new RateLimitConfig(),
        public array $routingRules = [],
        public bool $alertOnFailure = true,
        public bool $alertOnHardBounce = false,
        public array $alertEmails = [],
        public array $alertWebhooks = [],
        public string $slackWebhook = '',
        public string $discordWebhook = '',
        public string $teamsWebhook = '',
        public string $twilioAccountSid = '',
        public string $twilioAuthToken = '',
        public string $twilioFrom = '',
        public string $twilioTo = '',
        public string $pushConnectionName = '',
        public string $whatsappAccessToken = '',
        public string $whatsappBusinessAccountId = '',
        public string $whatsappPhoneNumberId = '',
        public string $whatsappTo = '',
        public array $forwardEmails = [],
        public array $disabledNotifications = [],
        public bool $doNotSend = false,
        public bool $hideAnnouncements = false,
        public bool $hideDeliveryErrors = false,
        public bool $hideDashboardWidget = false,
        public bool $allowUsageTracking = false,
        public bool $optimizeSending = false,
        public bool $uninstallData = false,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $connections = self::connections($data['connections'] ?? []);
        $primaryData = is_array($data['connection'] ?? null) ? $data['connection'] : [];
        $primary = $primaryData !== []
            ? ConnectionConfig::fromArray($primaryData, 'primary')
            : ($connections['primary'] ?? ConnectionConfig::fromArray([], 'primary'));
        $connections[$primary->id] = $primary;

        $backup = null;

        if (isset($data['backup_connection']) && is_array($data['backup_connection'])) {
            $backupConnection = ConnectionConfig::fromArray($data['backup_connection'], 'backup');
            $connections[$backupConnection->id] = $backupConnection;
            $backup = $backupConnection->id;
        }

        if (isset($data['backup_connection_id'])) {
            $backup = WordPressArray::string($data['backup_connection_id']);
            $backup = $backup !== '' ? $backup : null;
        }

        return new self(
            enabled: WordPressArray::bool($data['enabled'] ?? true),
            defaultConnection: WordPressArray::string($data['default_connection'] ?? 'primary') ?: 'primary',
            backupConnection: $backup,
            connections: $connections,
            loggingEnabled: WordPressArray::bool($data['logging_enabled'] ?? true),
            saveBody: WordPressArray::bool($data['save_body'] ?? true),
            saveAttachments: WordPressArray::bool($data['save_attachments'] ?? false),
            openTracking: WordPressArray::bool($data['open_tracking'] ?? false),
            clickTracking: WordPressArray::bool($data['click_tracking'] ?? false),
            weeklySummary: WordPressArray::bool($data['weekly_summary'] ?? false),
            reportsEnabled: WordPressArray::bool($data['reports_enabled'] ?? true),
            smartRoutingEnabled: WordPressArray::bool($data['smart_routing_enabled'] ?? true),
            rateLimit: RateLimitConfig::fromArray(is_array($data['rate_limit'] ?? null) ? $data['rate_limit'] : []),
            routingRules: self::routingRulesFromData($data['routing_rules'] ?? []),
            alertOnFailure: WordPressArray::bool($data['alert_on_failure'] ?? true),
            alertOnHardBounce: WordPressArray::bool($data['alert_on_hard_bounce'] ?? false),
            alertEmails: WordPressArray::stringList($data['alert_emails'] ?? []),
            alertWebhooks: WordPressArray::stringList($data['alert_webhooks'] ?? []),
            slackWebhook: WordPressArray::string($data['slack_webhook'] ?? ''),
            discordWebhook: WordPressArray::string($data['discord_webhook'] ?? ''),
            teamsWebhook: WordPressArray::string($data['teams_webhook'] ?? ''),
            twilioAccountSid: WordPressArray::string($data['twilio_account_sid'] ?? ''),
            twilioAuthToken: WordPressArray::string($data['twilio_auth_token'] ?? ''),
            twilioFrom: WordPressArray::string($data['twilio_from'] ?? ''),
            twilioTo: WordPressArray::string($data['twilio_to'] ?? ''),
            pushConnectionName: WordPressArray::string($data['push_connection_name'] ?? ''),
            whatsappAccessToken: WordPressArray::string($data['whatsapp_access_token'] ?? ''),
            whatsappBusinessAccountId: WordPressArray::string($data['whatsapp_business_account_id'] ?? ''),
            whatsappPhoneNumberId: WordPressArray::string($data['whatsapp_phone_number_id'] ?? ''),
            whatsappTo: WordPressArray::string($data['whatsapp_to'] ?? ''),
            forwardEmails: WordPressArray::stringList($data['forward_emails'] ?? []),
            disabledNotifications: WordPressArray::stringList($data['disabled_notifications'] ?? []),
            doNotSend: WordPressArray::bool($data['do_not_send'] ?? false),
            hideAnnouncements: WordPressArray::bool($data['hide_announcements'] ?? false),
            hideDeliveryErrors: WordPressArray::bool($data['hide_delivery_errors'] ?? false),
            hideDashboardWidget: WordPressArray::bool($data['hide_dashboard_widget'] ?? false),
            allowUsageTracking: WordPressArray::bool($data['allow_usage_tracking'] ?? false),
            optimizeSending: WordPressArray::bool($data['optimize_sending'] ?? false),
            uninstallData: WordPressArray::bool($data['uninstall_data'] ?? false),
        );
    }

    public function connection(string $id): ?ConnectionConfig
    {
        return $this->connections[$id] ?? null;
    }

    public function defaultConnection(): ConnectionConfig
    {
        return $this->connections[$this->defaultConnection]
            ?? $this->connections['primary']
            ?? ConnectionConfig::fromArray([], 'primary');
    }

    public function backupConnection(): ?ConnectionConfig
    {
        if ($this->backupConnection === null || $this->backupConnection === '') {
            return null;
        }

        return $this->connections[$this->backupConnection] ?? null;
    }

    public function notificationDisabled(string $notification): bool
    {
        return in_array($notification, $this->disabledNotifications, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled'                      => $this->enabled,
            'default_connection'           => $this->defaultConnection,
            'backup_connection_id'         => $this->backupConnection,
            'connections'                  => array_map(
                static fn (ConnectionConfig $connection): array => $connection->toArray(),
                $this->connections,
            ),
            'logging_enabled'              => $this->loggingEnabled,
            'save_body'                    => $this->saveBody,
            'save_attachments'             => $this->saveAttachments,
            'open_tracking'                => $this->openTracking,
            'click_tracking'               => $this->clickTracking,
            'weekly_summary'               => $this->weeklySummary,
            'reports_enabled'              => $this->reportsEnabled,
            'smart_routing_enabled'        => $this->smartRoutingEnabled,
            'rate_limit'                   => $this->rateLimit->toArray(),
            'routing_rules'                => $this->routingRulesToArray(),
            'alert_on_failure'             => $this->alertOnFailure,
            'alert_on_hard_bounce'         => $this->alertOnHardBounce,
            'alert_emails'                 => $this->alertEmails,
            'alert_webhooks'               => $this->alertWebhooks,
            'slack_webhook'                => $this->slackWebhook,
            'discord_webhook'              => $this->discordWebhook,
            'teams_webhook'                => $this->teamsWebhook,
            'twilio_account_sid'           => $this->twilioAccountSid,
            'twilio_auth_token'            => $this->twilioAuthToken,
            'twilio_from'                  => $this->twilioFrom,
            'twilio_to'                    => $this->twilioTo,
            'push_connection_name'         => $this->pushConnectionName,
            'whatsapp_access_token'        => $this->whatsappAccessToken,
            'whatsapp_business_account_id' => $this->whatsappBusinessAccountId,
            'whatsapp_phone_number_id'     => $this->whatsappPhoneNumberId,
            'whatsapp_to'                  => $this->whatsappTo,
            'forward_emails'               => $this->forwardEmails,
            'disabled_notifications'       => $this->disabledNotifications,
            'do_not_send'                  => $this->doNotSend,
            'hide_announcements'           => $this->hideAnnouncements,
            'hide_delivery_errors'         => $this->hideDeliveryErrors,
            'hide_dashboard_widget'        => $this->hideDashboardWidget,
            'allow_usage_tracking'         => $this->allowUsageTracking,
            'optimize_sending'             => $this->optimizeSending,
            'uninstall_data'               => $this->uninstallData,
        ];
    }

    /**
     * @param mixed $data
     * @return array<string, ConnectionConfig>
     */
    private static function connections($data): array
    {
        if (!is_array($data)) {
            return [];
        }

        $connections = [];

        foreach ($data as $id => $connectionData) {
            if (!is_array($connectionData)) {
                continue;
            }

            $connection = ConnectionConfig::fromArray($connectionData, is_string($id) ? $id : 'connection');
            $connections[$connection->id] = $connection;
        }

        return $connections;
    }

    /**
     * @param mixed $data
     * @return list<array<string, mixed>>
     */
    private static function routingRulesFromData($data): array
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($data)) {
            return [];
        }

        $rules = [];

        foreach ($data as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $rules[] = $rule;
        }

        return $rules;
    }

    /** @return list<array<string, mixed>> */
    private function routingRulesToArray(): array
    {
        $rules = [];

        foreach ($this->routingRules as $rule) {
            if (is_array($rule)) {
                $rules[] = $rule;
                continue;
            }

            if (!is_object($rule) || !method_exists($rule, 'toArray')) {
                continue;
            }

            $array = $rule->toArray();
            if (!is_array($array)) {
                continue;
            }

            $rules[] = $array;
        }

        return $rules;
    }
}
