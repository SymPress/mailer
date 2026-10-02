<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Database;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Application\MailerService;
use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Config\WordPressSettingsRepository;
use SymPress\Mailer\Hook\WordPressMailerBridge;
use SymPress\Mailer\Message\DefaultAttachmentPolicy;
use SymPress\Mailer\Message\NullEmailBodyProcessor;
use SymPress\Mailer\Message\SymfonyEmailFactory;
use SymPress\Mailer\Message\WordPressMailParser;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;
use SymPress\Mailer\Transport\DsnFactory;
use SymPress\Mailer\Transport\ProviderApiTransportFactory;
use SymPress\Mailer\Transport\SymfonyMailerFactory;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

final class AdminDeliverySettingsTest extends TestCase
{
    public function testDeliveryReadRetainsExplicitNetworkScopeCapabilityCheck(): void
    {
        $id = wp_insert_user(['user_login' => 'delivery-scope-admin', 'user_email' => 'delivery-scope@example.test', 'user_pass' => bin2hex(random_bytes(20)), 'role' => 'administrator']);
        self::assertIsInt($id);
        wp_set_current_user($id);
        set_current_screen('dashboard');
        $_POST = ['action' => 'sympress_mailer_save_settings', '_sympress_scope' => 'network'];
        try {
            $this->expectException(\RuntimeException::class);
            (new WordPressSettingsRepository('mailer_review_admin_scope'))->getForDelivery();
        } finally {
            $_POST = [];
            $GLOBALS['current_screen'] = null;
            wp_set_current_user(1);
        }
    }

    public function testSiteAdminDeliveryUsesNetworkDefaultsWhileEditingRemainsSiteScoped(): void
    {
        $option = 'mailer_review_admin_delivery';
        $network = ['enabled' => true, 'connection' => ['id' => 'primary', 'dsn' => 'null://null', 'from_email' => 'network@example.test', 'force_from' => true, 'password' => 'network-admin-delivery-canary']];
        update_site_option($option, $network);
        delete_option($option);
        $id = wp_insert_user(['user_login' => 'delivery-site-admin', 'user_email' => 'delivery-admin@example.test', 'user_pass' => bin2hex(random_bytes(20)), 'role' => 'administrator']);
        self::assertIsInt($id);
        wp_set_current_user($id);
        set_current_screen('dashboard');
        $_POST = [];
        self::assertTrue(is_admin());
        self::assertFalse(current_user_can('manage_network_options'));
        $repository = new WordPressSettingsRepository($option);
        self::assertSame('', $repository->get()->defaultConnection()->password);
        self::assertSame('', $repository->get()->defaultConnection()->fromEmail);
        self::assertSame($network, get_site_option($option));
        $transport = new class extends AbstractTransport {
            public ?SentMessage $sent = null;

            protected function doSend(SentMessage $message): void
            {
                $this->sent = $message;
            }

            public function __toString(): string
            {
                return 'review://local';
            }
        };
        $secrets = new EnvironmentConnectionSecretResolver();
        $mailer = new MailerService($repository, new SymfonyEmailFactory(new NullEmailBodyProcessor(), new DefaultAttachmentPolicy()), new SymfonyMailerFactory(new DsnFactory($secrets), new ProviderApiTransportFactory($secrets), $transport));
        $bridge = new WordPressMailerBridge($repository, new WordPressMailParser(), $mailer);
        add_filter('pre_wp_mail', $bridge->send(...), 2147483647, 2);
        try {
            self::assertTrue(wp_mail('recipient@example.test', 'Admin user notification', 'Disposable fixture message'));
            self::assertSame('network@example.test', $transport->sent?->getOriginalMessage()->getFrom()[0]->getAddress());
            self::assertSame('network-admin-delivery-canary', $repository->getForDelivery()->defaultConnection()->password);
            self::assertFalse(get_option($option, false));
            self::assertStringStartsWith('enc:v2:', get_site_option($option)['connection']['password']);
            self::assertSame('', $repository->get()->defaultConnection()->password);
            $networkAfterMigration = get_site_option($option);
            $repository->save(MailerSettings::fromArray(['connection' => ['from_email' => 'site@example.test', 'force_from' => true, 'password' => 'site-only-canary']]));
            self::assertTrue(wp_mail('recipient@example.test', 'Site override notification', 'Disposable fixture message'));
            self::assertSame('site@example.test', $transport->sent?->getOriginalMessage()->getFrom()[0]->getAddress());
            self::assertSame('site-only-canary', $repository->getForDelivery()->defaultConnection()->password);
            self::assertSame($networkAfterMigration, get_site_option($option));
        } finally {
            remove_filter('pre_wp_mail', $bridge->send(...), 2147483647);
            $_POST = [];
            $GLOBALS['current_screen'] = null;
            wp_set_current_user(1);
        }
    }
}
