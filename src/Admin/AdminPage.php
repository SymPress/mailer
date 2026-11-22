<?php

declare(strict_types=1);

namespace SymPress\Mailer\Admin;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Config\SettingsScope;
use SymPress\Mailer\Import\ConnectionImportService;
use SymPress\Mailer\Provider\ProviderDefinition;
use SymPress\Mailer\Provider\ProviderRegistryInterface;
use SymPress\Mailer\Secret\SecretFields;
use SymPress\Mailer\Support\WordPressArray;
use SymPress\Mailer\Validation\ConnectionHealthCheckerInterface;
use SymPress\Mailer\Validation\ConnectionValidatorInterface;

final readonly class AdminPage
{
    use AdminScreenHelpers;

    public function __construct(
        private SettingsRepositoryInterface $settingsRepository,
        private ProviderRegistryInterface $providers,
        private ConnectionValidatorInterface $validator,
        private ConnectionHealthCheckerInterface $healthChecker,
        private ConnectionImportService $imports,
        private ToolsPage $toolsPage,
    ) {
    }

    public function register(): void
    {
        if ($this->proPluginActive() || !function_exists('add_menu_page') || !function_exists('add_submenu_page')) {
            return;
        }

        add_menu_page(
            'SymPress Mailer',
            __('Mailer', 'sympress-mailer'),
            SettingsScope::network() ? 'manage_network_options' : 'manage_options',
            self::SLUG,
            $this->renderSettings(...),
            'dashicons-email-alt2',
            58,
        );

        add_submenu_page(
            self::SLUG,
            __('Settings', 'sympress-mailer'),
            __('Settings', 'sympress-mailer'),
            SettingsScope::network() ? 'manage_network_options' : 'manage_options',
            self::SLUG,
            $this->renderSettings(...),
        );

        add_submenu_page(
            self::SLUG,
            __('Tools', 'sympress-mailer'),
            __('Tools', 'sympress-mailer'),
            SettingsScope::network() ? 'manage_network_options' : 'manage_options',
            self::SLUG . '-tools',
            $this->toolsPage->renderTest(...),
        );
    }

    public function renderSettings(): void
    {
        if ($this->proPluginActive()) {
            return;
        }

        $this->assertCapability();
        $settings = $this->settingsRepository->get();
        $tab = $this->currentTab();

        $this->chromeStart(__('Settings', 'sympress-mailer'), $settings);
        $this->tabs($tab);
        $this->adminNotices();

        if ($tab === 'import') {
            $this->importTab();
            $this->chromeEnd();
            return;
        }

        echo '<form method="post" action="' . $this->attr($this->adminPostUrl()) . '">';
        echo '<input type="hidden" name="action" value="sympress_mailer_save_settings">';
        echo '<input type="hidden" name="_tab" value="' . $this->attr($tab) . '">';
        $this->nonce('sympress_mailer_save_settings');

        if ($tab === 'misc') {
            $this->miscTab($settings);
        } else {
            $this->generalTab($settings);
        }

        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Settings', 'sympress-mailer') . '</button></p>';
        echo '</form>';
        $this->chromeEnd();
    }

    public function saveSettings(): void
    {
        if ($this->proPluginActive()) {
            return;
        }

        $this->assertCapability();
        $this->checkNonce('sympress_mailer_save_settings');

        $post = WordPressArray::post();
        $tab = WordPressArray::string($post['_tab'] ?? 'general');
        $tab = $tab === 'misc' ? 'misc' : 'general';
        $data = $this->settingsRepository->get()->toArray();

        if ($tab === 'misc') {
            $data['do_not_send'] = WordPressArray::bool($post['do_not_send'] ?? false);
            $data['uninstall_data'] = WordPressArray::bool($post['uninstall_data'] ?? false);
        } else {
            $connection = $this->connectionFromPost(SecretFields::preserve($post, $this->settingsRepository->get()->defaultConnection()->toArray()));
            $validation = $this->validator->validate($connection);

            if (!$validation->valid()) {
                $this->failValidation($validation->message());
            }

            $this->assertConnectionHealthy($connection);
            $data['enabled'] = WordPressArray::bool($post['enabled'] ?? false);
            $data['default_connection'] = 'primary';
            $data['connection'] = $connection->toArray();
            $data['connections']['primary'] = $connection->toArray();
        }

        $this->settingsRepository->save(MailerSettings::fromArray($data));
        $args = ['tab' => $tab, 'updated' => '1'];

        if ($tab === 'general') {
            $args['health'] = 'ok';
        }

        $this->redirect(self::SLUG, $args);
    }

    public function importConnection(): void
    {
        if ($this->proPluginActive()) {
            return;
        }

        $this->assertCapability();
        $source = WordPressArray::string(WordPressArray::post()['source'] ?? '');
        $this->checkNonce('sympress_mailer_import_connection_' . $source);

        $candidate = $this->imports->find($source);

        if ($candidate === null) {
            $this->failValidation(__('No importable mailer connection was found for this source.', 'sympress-mailer'));
        }

        $validation = $this->validator->validate($candidate->connection);

        if (!$validation->valid()) {
            $this->failValidation($validation->message());
        }

        $this->assertConnectionHealthy($candidate->connection);
        $data = $this->settingsRepository->get()->toArray();
        $data['enabled'] = true;
        $data['default_connection'] = 'primary';
        $data['connection'] = $candidate->connection->toArray();
        $data['connections']['primary'] = $candidate->connection->toArray();
        $this->settingsRepository->save(MailerSettings::fromArray($data));
        $this->redirect(self::SLUG, ['tab' => 'general', 'imported' => $source, 'health' => 'ok']);
    }

    private function generalTab(MailerSettings $settings): void
    {
        $connection = $settings->defaultConnection();

        echo '<section class="spm-section">';
        echo '<h2>' . esc_html__('Primary Connection', 'sympress-mailer') . '</h2>';
        $this->switchRow('enabled', __('Enable Mailer', 'sympress-mailer'), $settings->enabled);

        echo '<div class="spm-provider-grid">';
        foreach ($this->providers->all() as $provider) {
            $checked = $connection->provider === $provider->key ? ' checked' : '';
            echo '<label class="spm-provider-card">';
            echo '<input type="radio" name="provider" value="' . $this->attr($provider->key) . '"' . $checked . '>';
            echo $this->providerLogo($provider);
            echo '<strong>' . $this->esc($provider->title) . '</strong><span>' . $this->esc($provider->type) . '</span>';
            echo '</label>';
        }
        echo '</div>';

        $this->providerHelp($connection);
        $this->select('key_store', __('Secret Source', 'sympress-mailer'), $connection->keyStore, ['encrypted_option' => __('Encrypted option', 'sympress-mailer'), 'env' => __('Environment / constants', 'sympress-mailer'), 'wp_config' => 'wp-config.php constants', 'config' => __('Kernel config / filter', 'sympress-mailer')]);
        $this->input('secret_prefix', __('Secret Prefix', 'sympress-mailer'), $connection->secretPrefix);
        if (in_array($connection->keyStore, ['env', 'wp_config', 'config'], true)) {
            echo '<p class="description">' . esc_html__('When a password, API key or DSN comes from this external source, the DSN, host, port, encryption and TLS settings are read from the same source and the values below are ignored. Without an external host, the provider\'s default endpoint is used.', 'sympress-mailer') . '</p>';
        }
        $this->input('dsn', __('Symfony DSN', 'sympress-mailer'), $connection->dsn);
        $this->input('host', __('SMTP Host', 'sympress-mailer'), $connection->host);
        $this->input('port', __('SMTP Port', 'sympress-mailer'), (string) $connection->port, 'number');
        $this->input('username', __('Username', 'sympress-mailer'), $connection->username);
        $this->input('password', __('Password', 'sympress-mailer'), $connection->password, 'password');
        $this->select('encryption', __('Encryption', 'sympress-mailer'), $connection->encryption, $this->providerFieldOptions($connection, 'encryption') ?: ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => __('None', 'sympress-mailer')]);
        $this->input('api_key', __('API Key', 'sympress-mailer'), $connection->apiKey);
        $this->input('api_secret', __('API Secret', 'sympress-mailer'), $connection->apiSecret, 'password');
        $this->input('domain', __('Domain', 'sympress-mailer'), $connection->domain);
        $regionOptions = $this->providerFieldOptions($connection, 'region');

        if ($regionOptions !== []) {
            $this->select('region', __('Region', 'sympress-mailer'), $connection->region, $regionOptions);
        } else {
            $this->input('region', __('Region', 'sympress-mailer'), $connection->region);
        }
        $this->input('tenant_id', __('Tenant ID', 'sympress-mailer'), $connection->tenantId);
        $this->input('from_email', __('From Email', 'sympress-mailer'), $connection->fromEmail, 'email');
        $this->input('from_name', __('From Name', 'sympress-mailer'), $connection->fromName);
        $this->switchRow('force_from', __('Force From Email', 'sympress-mailer'), $connection->forceFrom);
        $this->switchRow('force_from_name', __('Force From Name', 'sympress-mailer'), $connection->forceFromName);
        $this->switchRow('return_path', __('Set Return-Path', 'sympress-mailer'), $connection->returnPath);
        $this->switchRow('auto_tls', __('Auto TLS', 'sympress-mailer'), $connection->autoTls);
        $this->switchRow('verify_peer', __('Verify TLS Peer', 'sympress-mailer'), $connection->verifyPeer);
        echo '</section>';
    }

    private function miscTab(MailerSettings $settings): void
    {
        echo '<section class="spm-section">';
        echo '<h2>' . esc_html__('Misc', 'sympress-mailer') . '</h2>';
        $this->switchRow('do_not_send', __('Do Not Send', 'sympress-mailer'), $settings->doNotSend);
        $this->switchRow('uninstall_data', __('Delete Settings on Uninstall', 'sympress-mailer'), $settings->uninstallData);
        echo '</section>';
    }

    private function importTab(): void
    {
        $candidates = $this->imports->candidates();

        echo '<section class="spm-section">';
        echo '<h2>' . esc_html__('Import Existing SMTP Settings', 'sympress-mailer') . '</h2>';

        if ($candidates === []) {
            echo '<p>' . esc_html__('No supported SMTP plugin settings were detected. Supported import sources are Fluent SMTP, WP Mail SMTP, Easy WP SMTP and Post SMTP.', 'sympress-mailer') . '</p>';
            echo '</section>';
            return;
        }

        foreach ($candidates as $candidate) {
            echo '<div class="spm-card">';
            echo '<h3>' . $this->esc($candidate->title) . '</h3>';
            echo '<p>' . $this->esc($candidate->description) . '</p>';
            echo '<p><strong>' . $this->esc($candidate->connection->provider) . '</strong> ';
            echo $this->esc($candidate->connection->fromEmail !== '' ? $candidate->connection->fromEmail : $candidate->connection->host) . '</p>';
            echo '<form method="post" action="' . $this->attr($this->adminPostUrl()) . '">';
            echo '<input type="hidden" name="action" value="sympress_mailer_import_connection">';
            echo '<input type="hidden" name="source" value="' . $this->attr($candidate->source) . '">';
            $this->nonce('sympress_mailer_import_connection_' . $candidate->source);
            echo '<button type="submit" class="button button-primary">' . esc_html__('Import Connection', 'sympress-mailer') . '</button>';
            echo '</form>';
            echo '</div>';
        }

        echo '</section>';
    }

    /** @param array<string, mixed> $post */
    private function connectionFromPost(array $post): ConnectionConfig
    {
        return ConnectionConfig::fromArray(
            $this->withProviderDefaults(
                [
                'id'              => 'primary',
                'name'            => 'Primary',
                'provider'        => $post['provider'] ?? 'smtp',
                'dsn'             => $post['dsn'] ?? '',
                'host'            => $post['host'] ?? '',
                'port'            => $post['port'] ?? 587,
                'username'        => $post['username'] ?? '',
                'password'        => $post['password'] ?? '',
                'encryption'      => $post['encryption'] ?? 'tls',
                'api_key'         => $post['api_key'] ?? '',
                'api_secret'      => $post['api_secret'] ?? '',
                'domain'          => $post['domain'] ?? '',
                'region'          => $post['region'] ?? '',
                'tenant_id'       => $post['tenant_id'] ?? '',
                'from_email'      => $post['from_email'] ?? '',
                'from_name'       => $post['from_name'] ?? '',
                'force_from'      => $post['force_from'] ?? false,
                'force_from_name' => $post['force_from_name'] ?? false,
                'return_path'     => $post['return_path'] ?? false,
                'auto_tls'        => $post['auto_tls'] ?? false,
                'verify_peer'     => $post['verify_peer'] ?? false,
                'key_store'       => $post['key_store'] ?? 'encrypted_option',
                'secret_prefix'   => $post['secret_prefix'] ?? '',
                ],
            ),
            'primary',
        );
    }

    private function tabs(string $current): void
    {
        echo '<nav class="nav-tab-wrapper spm-tabs" aria-label="Mailer settings tabs">';
        foreach (['general' => __('General', 'sympress-mailer'), 'import' => __('Import', 'sympress-mailer'), 'misc' => __('Misc', 'sympress-mailer')] as $tab => $label) {
            $class = $tab === $current ? ' nav-tab-active' : '';
            $currentAttribute = $tab === $current ? ' aria-current="page"' : '';
            $url = $this->adminUrl('admin.php', ['page' => self::SLUG, 'tab' => $tab]);
            echo '<a class="nav-tab' . $this->attr($class) . '" href="' . $this->attr($url) . '"' . $currentAttribute . '>' . $this->esc($label) . '</a>';
        }
        echo '</nav>';
    }

    private function currentTab(): string
    {
        $tab = WordPressArray::string(WordPressArray::get()['tab'] ?? 'general');

        return in_array($tab, ['general', 'import', 'misc'], true) ? $tab : 'general';
    }

    private function adminNotices(): void
    {
        $get = WordPressArray::get();

        if (WordPressArray::bool($get['updated'] ?? false)) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Settings saved.', 'sympress-mailer') . '</p></div>';
        }

        $imported = WordPressArray::string($get['imported'] ?? '');

        if ($imported !== '') {
            echo '<div class="notice notice-success inline"><p>Imported settings from ' . $this->esc($imported) . '.</p></div>';
        }

        if (!(WordPressArray::string($get['health'] ?? '') === 'ok')) {
            return;
        }

        echo '<div class="notice notice-success inline"><p>' . esc_html__('Connection health check passed.', 'sympress-mailer') . '</p></div>';
    }

    private function providerHelp(ConnectionConfig $connection): void
    {
        $provider = $this->providers->get($connection->provider);

        if ($provider === null || $provider->docsUrl === '') {
            return;
        }

        echo '<p class="description"><a href="' . $this->attr($provider->docsUrl) . '" target="_blank" rel="noopener noreferrer">';
        echo $this->esc($provider->title) . ' setup documentation</a></p>';
    }

    private function providerLogo(ProviderDefinition $provider): string
    {
        $logo = $provider->logo !== '' ? $provider->logo : strtoupper(substr($provider->title, 0, 2));

        return '<span class="spm-provider-logo" aria-hidden="true">' . $this->esc($logo) . '</span>';
    }

    /** @param array<string, string> $options */
    private function select(string $name, string $label, string $value, array $options): void
    {
        echo '<label class="spm-field"><span>' . $this->esc($label) . '</span>';
        echo '<select name="' . $this->attr($name) . '">';
        foreach ($options as $key => $optionLabel) {
            echo '<option value="' . $this->attr($key) . '"' . ($key === $value ? ' selected' : '') . '>';
            echo $this->esc($optionLabel) . '</option>';
        }
        echo '</select></label>';
    }

    private function failValidation(string $message): never
    {
        if (function_exists('wp_die')) {
            wp_die(nl2br($this->esc($message)), __('SymPress Mailer validation failed', 'sympress-mailer'), ['response' => 422]);
        }

        throw new \InvalidArgumentException($message);
    }

    private function assertConnectionHealthy(ConnectionConfig $connection): void
    {
        $health = $this->healthChecker->check($connection);

        if ($health->healthy) {
            return;
        }

        $this->failValidation($health->message);
    }

    /**
     * @param array<string, mixed> $connection
     * @return array<string, mixed>
     */
    private function withProviderDefaults(array $connection): array
    {
        $provider = $this->providers->get(WordPressArray::string($connection['provider'] ?? ''));

        return $provider?->applyDefaults($connection) ?? $connection;
    }

    /** @return array<string, string> */
    private function providerFieldOptions(ConnectionConfig $connection, string $field): array
    {
        $provider = $this->providers->get($connection->provider);
        $options = $provider?->options[$field] ?? [];

        return is_array($options) ? $options : [];
    }
}
