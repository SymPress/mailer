<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Database;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Admin\ToolsPage;
use SymPress\Mailer\Application\MailerService;
use SymPress\Mailer\Application\TestEmailSender;
use SymPress\Mailer\Config\SettingsScope;
use SymPress\Mailer\Config\WordPressSettingsRepository;
use SymPress\Mailer\Message\DefaultAttachmentPolicy;
use SymPress\Mailer\Message\NullEmailBodyProcessor;
use SymPress\Mailer\Message\SymfonyEmailFactory;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;
use SymPress\Mailer\Transport\DsnFactory;
use SymPress\Mailer\Transport\ProviderApiTransportFactory;
use SymPress\Mailer\Transport\SymfonyMailerFactory;
use SymPress\Mailer\Validation\SmtpConnectionProbe;

final class AdminTestEmailFailureTest extends TestCase
{
    private const string OPTION = 'mailer_review_test_email_failure';
    // Nothing listens on port 1, so delivery fails at once with the host in the message.
    private const array FAILING = ['enabled' => true, 'connection' => ['id' => 'primary', 'provider' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'from_email' => 'sender@example.test']];

    public function testSiteAdminGetsNoFailureReasonForTheInheritedNetworkConnection(): void
    {
        update_site_option(self::OPTION, self::FAILING);
        delete_option(self::OPTION);

        self::assertFalse($this->sendTestAsAdmin('test-email-inherited'));
    }

    public function testSiteAdminGetsTheFailureReasonForTheSitesOwnConnection(): void
    {
        update_site_option(self::OPTION, self::FAILING);
        update_option(self::OPTION, self::FAILING, false);

        self::assertStringContainsString('127.0.0.1:1', (string) $this->sendTestAsAdmin('test-email-own'));
    }

    public function testNetworkAdminGetsTheFailureReasonForTheNetworkConnection(): void
    {
        update_site_option(self::OPTION, self::FAILING);
        delete_option(self::OPTION);

        self::assertStringContainsString('127.0.0.1:1', (string) $this->sendTestAsAdmin('test-email-network', true));
    }

    private function sendTestAsAdmin(string $login, bool $network = false): mixed
    {
        $id = wp_insert_user(['user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => bin2hex(random_bytes(20)), 'role' => 'administrator']);
        self::assertIsInt($id);
        if ($network) {
            grant_super_admin($id);
        }
        $key = 'sympress_mailer_test_failure_' . $id;
        $repository = new WordPressSettingsRepository(self::OPTION);
        $secrets = new EnvironmentConnectionSecretResolver();
        $mailerFactory = new SymfonyMailerFactory(new DsnFactory($secrets), new ProviderApiTransportFactory($secrets));
        $mailer = new MailerService($repository, new SymfonyEmailFactory(new NullEmailBodyProcessor(), new DefaultAttachmentPolicy()), $mailerFactory);
        $tools = new ToolsPage($repository, new TestEmailSender($mailer, $secrets), new SmtpConnectionProbe($mailerFactory, $secrets));
        $redirect = static function (): never {
            throw new \RuntimeException('review-redirect-intercepted');
        };
        add_filter('wp_redirect', $redirect);
        try {
            wp_set_current_user($id);
            set_current_screen('dashboard');
            self::assertSame($network, current_user_can('manage_network_options'));
            $_POST = ['action' => 'sympress_mailer_send_test', '_sympress_scope' => $network ? 'network' : 'site', 'to' => 'recipient@example.test'];
            $_POST['_wpnonce'] = wp_create_nonce(SettingsScope::nonce('sympress_mailer_send_test'));
            $_REQUEST = $_POST;
            try {
                $tools->sendTest();
                self::fail('Expected a redirect back to the Tools screen.');
            } catch (\RuntimeException $error) {
                self::assertSame('review-redirect-intercepted', $error->getMessage());
            }

            return get_transient($key);
        } finally {
            remove_filter('wp_redirect', $redirect);
            delete_transient($key);
            delete_option(self::OPTION);
            delete_site_option(self::OPTION);
            $_POST = [];
            $_REQUEST = [];
            $GLOBALS['current_screen'] = null;
            wp_set_current_user(1);
            revoke_super_admin($id);
            wpmu_delete_user($id);
        }
    }
}
