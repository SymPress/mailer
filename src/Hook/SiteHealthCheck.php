<?php

declare(strict_types=1);

namespace SymPress\Mailer\Hook;

use SymPress\Mailer\Config\DeliverySettings;
use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Provider\ProviderRegistryInterface;
use SymPress\Mailer\Validation\ConnectionValidatorInterface;

/** Reports the mail delivery status under Tools > Site Health, without network I/O. */
final readonly class SiteHealthCheck
{
    private const string TEST = 'sympress_mailer_delivery';

    public function __construct(
        private SettingsRepositoryInterface $settingsRepository,
        private ConnectionValidatorInterface $validator,
        private ProviderRegistryInterface $providers,
    ) {
    }

    /**
     * @param array<string, array<string, mixed>> $tests
     * @return array<string, array<string, mixed>>
     */
    public function register(array $tests): array
    {
        $tests['direct'][self::TEST] = [
            'label' => __('SymPress Mailer delivery', 'sympress-mailer'),
            'test'  => [$this, 'run'],
        ];

        return $tests;
    }

    /** @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} */
    public function run(): array
    {
        try {
            $settings = DeliverySettings::read($this->settingsRepository);
        } catch (\Throwable) {
            // The exception may name key material or storage details, so it is not shown.
            return $this->result('critical', __('The mailer settings cannot be read', 'sympress-mailer'), __('Emails cannot be delivered because the stored mail credentials cannot be decrypted, for example after the encryption key was removed or changed. Restore the key or enter the credentials again.', 'sympress-mailer'));
        }

        if (!$settings->enabled) {
            return $this->result('recommended', __('SymPress Mailer is disabled', 'sympress-mailer'), __('WordPress sends emails with its default mail function instead of the configured connection.', 'sympress-mailer'));
        }

        if ($settings->doNotSend) {
            return $this->result(wp_get_environment_type() === 'production' ? 'critical' : 'recommended', __('Do Not Send is enabled', 'sympress-mailer'), __('Emails are accepted but not delivered to anyone.', 'sympress-mailer'));
        }

        $connection = $settings->defaultConnection();
        $validation = $this->validator->validate($connection);

        if (!$validation->valid()) {
            return $this->result('critical', __('The mail connection is invalid', 'sympress-mailer'), __('Emails cannot be delivered until these connection settings are fixed:', 'sympress-mailer'), explode("\n", $validation->message()));
        }

        $provider = $this->providers->get($connection->provider)->title ?? $connection->provider;

        /* translators: %s: the mail provider name, e.g. "Postmark". */
        return $this->result('good', __('Emails are routed through SymPress Mailer', 'sympress-mailer'), sprintf(__('Emails are sent with the %s connection.', 'sympress-mailer'), $provider));
    }

    /**
     * @param list<string> $details
     * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}
     */
    private function result(string $status, string $label, string $description, array $details = []): array
    {
        $html = '<p>' . esc_html($description) . '</p>';

        if ($details !== []) {
            $html .= '<ul><li>' . implode('</li><li>', array_map(esc_html(...), $details)) . '</li></ul>';
        }

        return [
            'label'       => $label,
            'status'      => $status,
            'badge'       => ['label' => __('Email', 'sympress-mailer'), 'color' => 'blue'],
            'description' => $html,
            'actions'     => $status === 'good' ? '' : sprintf('<p><a href="%s">%s</a></p>', esc_url(admin_url('admin.php?page=sympress-mailer')), esc_html(__('Open the mailer settings', 'sympress-mailer'))),
            'test'        => self::TEST,
        ];
    }
}
