<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Database;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Admin\AdminPage;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Config\SettingsScope;
use SymPress\Mailer\Config\WordPressSettingsRepository;
use SymPress\Mailer\Provider\DefaultProviderRegistry;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;
use SymPress\Mailer\Validation\ConnectionHealth;
use SymPress\Mailer\Validation\ConnectionHealthCheckerInterface;
use SymPress\Mailer\Validation\DefaultConnectionValidator;

final class AdminSettingsFormTest extends TestCase
{
    private const string OPTION = 'mailer_review_settings_form';
    private const string STASH = 'sympress_mailer_rejected_settings_1';

    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $GLOBALS['current_screen'] = null;
        wp_set_current_user(1);
        delete_transient(self::STASH);
        (new WordPressSettingsRepository(self::OPTION))->save(MailerSettings::fromArray(['enabled' => false, 'connection' => [
            'provider' => 'smtp', 'host' => 'stored.example.test', 'from_email' => 'stored@example.test', 'auto_tls' => true,
            'password' => 'stored-password-canary', 'username' => 'stored-user-canary',
        ]]));
    }

    public function testRejectedSaveKeepsSubmittedValuesButNeverSecrets(): void
    {
        $before = get_option(self::OPTION);
        $admin = $this->admin(new ConnectionHealth(true, 'unused'));
        $url = $this->submit($admin, [
            'enabled' => '1', 'provider' => 'smtp', 'host' => 'submitted.example.test', 'port' => '2525', 'encryption' => 'tls',
            'from_email' => 'not-an-email', 'from_name' => 'Submitted Name',
            'password' => 'submitted-password-canary', 'username' => 'submitted-user-canary', 'api_key' => 'submitted-api-canary',
        ]);

        self::assertStringContainsString('tab=general', $url);
        self::assertStringContainsString('invalid=1', $url);
        self::assertSame($before, get_option(self::OPTION));
        $stash = serialize(get_transient(self::STASH));
        self::assertStringContainsString('Must be a valid email address.', $stash);
        $this->assertNoCanary($stash);

        $html = $this->render($admin);
        self::assertStringContainsString('<li>auto_tls: Enable Auto TLS or SSL encryption; credentials must not be sent in plaintext.</li>', $html);
        self::assertStringContainsString('<li>from_email: Must be a valid email address.</li>', $html);
        foreach (['submitted.example.test', 'value="2525"', 'not-an-email', 'Submitted Name', 'name="enabled" value="1" checked'] as $submitted) {
            self::assertStringContainsString($submitted, $html);
        }
        self::assertStringContainsString('spm-status is-off', $html);
        $this->assertNoCanary($html);
        self::assertFalse(get_transient(self::STASH));

        $again = $this->render($admin);
        self::assertStringNotContainsString('notice-error', $again);
        self::assertStringContainsString('stored.example.test', $again);
        self::assertStringNotContainsString('submitted.example.test', $again);
        $this->assertNoCanary($again);
    }

    public function testFailedHealthCheckSavesNothingAndShowsTheEscapedReason(): void
    {
        $before = get_option(self::OPTION);
        $admin = $this->admin(new ConnectionHealth(false, 'Health <b>failure</b>'));
        $this->submit($admin, [
            'provider' => 'smtp', 'host' => 'healthy.example.test', 'port' => '587', 'encryption' => 'tls', 'auto_tls' => '1',
            'from_email' => 'sender@example.test', 'password' => 'submitted-password-canary',
        ]);

        self::assertSame($before, get_option(self::OPTION));
        $html = $this->render($admin);
        self::assertStringContainsString('<li>Health &lt;b&gt;failure&lt;/b&gt;</li>', $html);
        self::assertStringContainsString('healthy.example.test', $html);
        $this->assertNoCanary($html);
    }

    private function admin(ConnectionHealth $health): AdminPage
    {
        $providers = new DefaultProviderRegistry();
        $admin = (new \ReflectionClass(AdminPage::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($admin, 'settingsRepository'))->setValue($admin, new WordPressSettingsRepository(self::OPTION));
        (new \ReflectionProperty($admin, 'providers'))->setValue($admin, $providers);
        (new \ReflectionProperty($admin, 'validator'))->setValue($admin, new DefaultConnectionValidator($providers, new EnvironmentConnectionSecretResolver()));
        (new \ReflectionProperty($admin, 'healthChecker'))->setValue($admin, new readonly class ($health) implements ConnectionHealthCheckerInterface {
            public function __construct(private ConnectionHealth $health)
            {
            }

            public function check(ConnectionConfig $connection): ConnectionHealth
            {
                return $this->health;
            }
        });

        return $admin;
    }

    /** @param array<string, string> $post */
    private function submit(AdminPage $admin, array $post): string
    {
        $_POST = ['action' => 'sympress_mailer_save_settings', '_sympress_scope' => 'site', '_tab' => 'general', ...$post];
        $_POST['_wpnonce'] = wp_create_nonce(SettingsScope::nonce('sympress_mailer_save_settings'));
        $_REQUEST = $_POST;
        $location = '';
        $redirect = static function (string $url) use (&$location): never {
            $location = $url;
            throw new \RuntimeException('review-redirect-intercepted');
        };
        add_filter('wp_redirect', $redirect);
        try {
            $admin->saveSettings();
            self::fail('Expected a redirect back to the form.');
        } catch (\RuntimeException $error) {
            self::assertSame('review-redirect-intercepted', $error->getMessage());
        } finally {
            remove_filter('wp_redirect', $redirect);
            $_POST = [];
            $_REQUEST = [];
        }

        return $location;
    }

    private function render(AdminPage $admin): string
    {
        $_GET = ['page' => 'sympress-mailer', 'tab' => 'general', 'invalid' => '1'];
        ob_start();
        try {
            $admin->renderSettings();
        } finally {
            $html = (string) ob_get_clean();
            $_GET = [];
        }

        return $html;
    }

    private function assertNoCanary(string $haystack): void
    {
        foreach (['submitted-password-canary', 'submitted-user-canary', 'submitted-api-canary', 'stored-password-canary', 'stored-user-canary'] as $secret) {
            self::assertStringNotContainsString($secret, $haystack);
        }
    }
}
