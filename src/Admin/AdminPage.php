<?php

declare(strict_types=1);

namespace SymPress\Mailer\Admin;

use SymPress\Mailer\Application\TestEmailSender;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Import\ConnectionImportService;
use SymPress\Mailer\Provider\ProviderDefinition;
use SymPress\Mailer\Provider\ProviderRegistryInterface;
use SymPress\Mailer\Support\WordPressArray;
use SymPress\Mailer\Validation\ConnectionHealthCheckerInterface;
use SymPress\Mailer\Validation\ConnectionValidatorInterface;

final readonly class AdminPage
{
    private const string SLUG = 'sympress-mailer';
    private const string PRO_ENTRY = 'mailer-pro/mailer-pro.php';

    public function __construct(
        private SettingsRepositoryInterface $settingsRepository,
        private TestEmailSender $testEmailSender,
        private ProviderRegistryInterface $providers,
        private ConnectionValidatorInterface $validator,
        private ConnectionHealthCheckerInterface $healthChecker,
        private ConnectionImportService $imports,
    ) {
    }

    public function register(): void
    {
        if ($this->proPluginActive() || !function_exists('add_menu_page') || !function_exists('add_submenu_page')) {
            return;
        }

        add_menu_page(
            'SymPress Mailer',
            'Mailer',
            'manage_options',
            self::SLUG,
            $this->renderSettings(...),
            'dashicons-email-alt2',
            58,
        );

        add_submenu_page(
            self::SLUG,
            'Settings',
            'Settings',
            'manage_options',
            self::SLUG,
            $this->renderSettings(...),
        );

        add_submenu_page(
            self::SLUG,
            'Tools',
            'Tools',
            'manage_options',
            self::SLUG . '-tools',
            $this->renderTest(...),
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

        $this->chromeStart('Settings', $settings);
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

        echo '<p><button type="submit" class="button button-primary">Save Settings</button></p>';
        echo '</form>';
        $this->chromeEnd();
    }

    public function renderTest(): void
    {
        if ($this->proPluginActive()) {
            return;
        }

        $this->assertCapability();
        $settings = $this->settingsRepository->get();

        $this->chromeStart('Tools', $settings);

        $status = WordPressArray::string(WordPressArray::get()['sympress_mailer_test'] ?? '');
        if ($status !== '') {
            $class = $status === 'sent' ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $this->attr($class) . ' inline"><p>Test email ' . $this->esc($status) . '.</p></div>';
        }

        echo '<section class="spm-section">';
        echo '<h2>Email Test</h2>';
        echo '<form method="post" action="' . $this->attr($this->adminPostUrl()) . '">';
        echo '<input type="hidden" name="action" value="sympress_mailer_send_test">';
        $this->nonce('sympress_mailer_send_test');
        $this->input('to', 'Recipient', $this->defaultRecipient(), 'email');
        echo '<p><button type="submit" class="button button-primary">Send Test Email</button></p>';
        echo '</form>';
        echo '</section>';
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
            $connection = $this->connectionFromPost($post);
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

    public function sendTest(): void
    {
        if ($this->proPluginActive()) {
            return;
        }

        $this->assertCapability();
        $this->checkNonce('sympress_mailer_send_test');

        $to = WordPressArray::string(WordPressArray::post()['to'] ?? '');
        $result = $to !== '' ? $this->testEmailSender->send($to) : null;
        $status = $result?->accepted === true ? 'sent' : 'failed';

        $this->redirect(self::SLUG . '-tools', ['sympress_mailer_test' => $status]);
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
            $this->failValidation('No importable mailer connection was found for this source.');
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
        echo '<h2>Primary Connection</h2>';
        $this->switchRow('enabled', 'Enable Mailer', $settings->enabled);

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
        $this->select('key_store', 'Secret Source', $connection->keyStore, ['option' => 'Stored option', 'encrypted_option' => 'Encrypted option', 'env' => 'Environment / constants', 'wp_config' => 'wp-config.php constants', 'config' => 'Kernel config / filter']);
        $this->input('secret_prefix', 'Secret Prefix', $connection->secretPrefix);
        $this->input('dsn', 'Symfony DSN', $connection->dsn);
        $this->input('host', 'SMTP Host', $connection->host);
        $this->input('port', 'SMTP Port', (string) $connection->port, 'number');
        $this->input('username', 'Username', $connection->username);
        $this->input('password', 'Password', $connection->password, 'password');
        $this->select('encryption', 'Encryption', $connection->encryption, $this->providerFieldOptions($connection, 'encryption') ?: ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None']);
        $this->input('api_key', 'API Key', $connection->apiKey);
        $this->input('api_secret', 'API Secret', $connection->apiSecret, 'password');
        $this->input('domain', 'Domain', $connection->domain);
        $regionOptions = $this->providerFieldOptions($connection, 'region');

        if ($regionOptions !== []) {
            $this->select('region', 'Region', $connection->region, $regionOptions);
        } else {
            $this->input('region', 'Region', $connection->region);
        }
        $this->input('tenant_id', 'Tenant ID', $connection->tenantId);
        $this->input('from_email', 'From Email', $connection->fromEmail, 'email');
        $this->input('from_name', 'From Name', $connection->fromName);
        $this->switchRow('force_from', 'Force From Email', $connection->forceFrom);
        $this->switchRow('force_from_name', 'Force From Name', $connection->forceFromName);
        $this->switchRow('return_path', 'Set Return-Path', $connection->returnPath);
        $this->switchRow('auto_tls', 'Auto TLS', $connection->autoTls);
        $this->switchRow('verify_peer', 'Verify TLS Peer', $connection->verifyPeer);
        echo '</section>';
    }

    private function miscTab(MailerSettings $settings): void
    {
        echo '<section class="spm-section">';
        echo '<h2>Misc</h2>';
        $this->switchRow('do_not_send', 'Do Not Send', $settings->doNotSend);
        $this->switchRow('uninstall_data', 'Delete Settings on Uninstall', $settings->uninstallData);
        echo '</section>';
    }

    private function importTab(): void
    {
        $candidates = $this->imports->candidates();

        echo '<section class="spm-section">';
        echo '<h2>Import Existing SMTP Settings</h2>';

        if ($candidates === []) {
            echo '<p>No supported SMTP plugin settings were detected. Supported import sources are Fluent SMTP, WP Mail SMTP, Easy WP SMTP and Post SMTP.</p>';
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
            echo '<button type="submit" class="button button-primary">Import Connection</button>';
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
                'key_store'       => $post['key_store'] ?? 'option',
                'secret_prefix'   => $post['secret_prefix'] ?? '',
                ],
            ),
            'primary',
        );
    }

    private function chromeStart(string $title, MailerSettings $settings): void
    {
        echo '<div class="wrap sympress-mailer">';
        echo '<section class="spm-section spm-hero">';
        echo '<div><h1>SymPress Mailer</h1><p>Symfony Mailer delivery for WordPress.</p></div>';
        echo '<span class="spm-status ' . ($settings->enabled ? 'is-on' : 'is-off') . '">';
        echo $settings->enabled ? 'Enabled' : 'Disabled';
        echo '</span></section>';
        echo '<section class="spm-section spm-page-title"><h2>' . $this->esc($title) . '</h2></section>';
    }

    private function chromeEnd(): void
    {
        echo '</div>';
    }

    private function tabs(string $current): void
    {
        echo '<nav class="nav-tab-wrapper spm-tabs" aria-label="Mailer settings tabs">';
        foreach (['general' => 'General', 'import' => 'Import', 'misc' => 'Misc'] as $tab => $label) {
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
            echo '<div class="notice notice-success inline"><p>Settings saved.</p></div>';
        }

        $imported = WordPressArray::string($get['imported'] ?? '');

        if ($imported !== '') {
            echo '<div class="notice notice-success inline"><p>Imported settings from ' . $this->esc($imported) . '.</p></div>';
        }

        if (!(WordPressArray::string($get['health'] ?? '') === 'ok')) {
            return;
        }

        echo '<div class="notice notice-success inline"><p>Connection health check passed.</p></div>';
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

    private function switchRow(string $name, string $label, bool $checked): void
    {
        echo '<label class="spm-switch-row"><span>' . $this->esc($label) . '</span><span class="spm-switch">';
        echo '<input type="checkbox" name="' . $this->attr($name) . '" value="1"' . ($checked ? ' checked' : '') . '>';
        echo '<span></span></span></label>';
    }

    private function input(string $name, string $label, string $value, string $type = 'text'): void
    {
        echo '<label class="spm-field"><span>' . $this->esc($label) . '</span>';
        echo '<input type="' . $this->attr($type) . '" name="' . $this->attr($name) . '" value="' . $this->attr($value) . '">';
        echo '</label>';
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

    private function assertCapability(): void
    {
        if (!function_exists('current_user_can') || current_user_can('manage_options')) {
            return;
        }

        if (function_exists('wp_die')) {
            wp_die('Insufficient permissions.');
        }

        throw new \RuntimeException('Insufficient permissions.');
    }

    private function checkNonce(string $action): void
    {
        if (!function_exists('check_admin_referer')) {
            return;
        }

        check_admin_referer($action);
    }

    private function failValidation(string $message): never
    {
        if (function_exists('wp_die')) {
            wp_die(nl2br($this->esc($message)), 'SymPress Mailer validation failed', ['response' => 422]);
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

    private function nonce(string $action): void
    {
        if (!function_exists('wp_nonce_field')) {
            return;
        }

        wp_nonce_field($action);
    }

    /** @param array<string, string> $args */
    private function redirect(string $page, array $args = []): void
    {
        $url = $this->adminUrl('admin.php', ['page' => $page, ...$args]);

        if (function_exists('wp_safe_redirect')) {
            wp_safe_redirect($url);
        } else {
            header('Location: ' . $url);
        }

        exit;
    }

    /** @param array<string, string> $args */
    private function adminUrl(string $path, array $args = []): string
    {
        $url = function_exists('admin_url') ? admin_url($path) : '/wp-admin/' . ltrim($path, '/');

        return $args === []
            ? $url
            : $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($args);
    }

    private function adminPostUrl(): string
    {
        return $this->adminUrl('admin-post.php');
    }

    private function defaultRecipient(): string
    {
        return function_exists('get_option') ? (string) get_option('admin_email', '') : '';
    }

    private function proPluginActive(): bool
    {
        if (!function_exists('get_option')) {
            return false;
        }

        $active = get_option('active_plugins', []);

        if (is_array($active) && in_array(self::PRO_ENTRY, $active, true)) {
            return true;
        }

        if (!function_exists('is_multisite') || !is_multisite() || !function_exists('get_site_option')) {
            return false;
        }

        $network = get_site_option('active_sitewide_plugins', []);

        return is_array($network) && array_key_exists(self::PRO_ENTRY, $network);
    }

    private function esc(string $value): string
    {
        return function_exists('esc_html') ? esc_html($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function attr(string $value): string
    {
        return function_exists('esc_attr') ? esc_attr($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
