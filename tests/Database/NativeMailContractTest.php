<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Database;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Config\WordPressSettingsRepository;
use SymPress\Mailer\Config\SettingsScope;
use SymPress\Mailer\Application\MailerService;
use SymPress\Mailer\Application\MailerInterface;
use SymPress\Mailer\Admin\AdminPage;
use SymPress\Mailer\Hook\WordPressMailerBridge;
use SymPress\Mailer\Message\WordPressMail;
use SymPress\Mailer\Message\WordPressMailParser;
use SymPress\Mailer\Message\SymfonyEmailFactory;
use SymPress\Mailer\Message\NullEmailBodyProcessor;
use SymPress\Mailer\Message\DefaultAttachmentPolicy;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;
use SymPress\Mailer\Transport\SymfonyMailerFactory;
use SymPress\Mailer\Transport\DsnFactory;
use SymPress\Mailer\Transport\ProviderApiTransportFactory;
use SymPress\Mailer\Value\SendResult;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\SentMessage;

final class NativeMailContractTest extends TestCase
{
    protected function setUp(): void
    {
        $_POST = [];
        $_REQUEST = [];
        $GLOBALS['current_screen'] = null;
        wp_set_current_user(1);
        foreach (['pre_wp_mail','wp_mail','wp_mail_from','wp_mail_from_name','wp_mail_content_type','wp_mail_charset','wp_mail_succeeded','wp_mail_failed'] as $hook) {
            remove_all_filters($hook);
        }
    }

    public function testHtmlTextAlternativeUsesNativeWordPressScriptAndStyleRemoval(): void
    {
        $factory = new SymfonyEmailFactory(new NullEmailBodyProcessor(), new DefaultAttachmentPolicy());
        $email = $factory->create(
            new \SymPress\Mailer\Message\WordPressMail(['reader@example.test'], 'HTML', '<style>private-style</style><script>private-script</script><p>Hello &amp; welcome</p>', contentType: 'text/html'),
            new \SymPress\Mailer\Config\ConnectionConfig(id: 'primary', fromEmail: 'sender@example.test'),
            new MailerSettings(),
            'archive-text-regression',
        );
        self::assertSame('Hello & welcome', $email->getTextBody());
        self::assertStringContainsString('<p>Hello &amp; welcome</p>', $email->getHtmlBody());
    }

    public function testPlaintextOptionsMigrateAndPasswordWhitespaceSurvives(): void
    {
        update_option('mailer_review_secrets', ['connection' => ['id'=>'primary','provider'=>'smtp','password'=>"  option-canary  ",'key_store'=>'option']]);
        $repository = new WordPressSettingsRepository('mailer_review_secrets');
        self::assertSame('  option-canary  ', $repository->get()->defaultConnection()->password);
        $stored = get_option('mailer_review_secrets');
        self::assertStringStartsWith('enc:v2:', $stored['connection']['password']);
        self::assertStringNotContainsString('option-canary', serialize($stored));
        self::assertSame('encrypted_option', $stored['connection']['key_store']);
    }

    public function testRejectedSettingsWriteFailsClosed(): void
    {
        $repository = new WordPressSettingsRepository('mailer_review_rejected');
        update_option('mailer_review_rejected', ['enabled'=>false]);
        $reject = static fn ($value, $old) => $old;
        add_filter('pre_update_option_mailer_review_rejected', $reject, 10, 2);
        try {
            $repository->save(MailerSettings::fromArray(['connection'=>['password'=>'never-stored-canary']]));
            self::fail('A rejected option write must fail closed.');
        } catch (\RuntimeException) {
            self::assertSame(['enabled'=>false], get_option('mailer_review_rejected'));
        } finally {
            remove_filter('pre_update_option_mailer_review_rejected', $reject, 10);
        }
    }

    public function testKeyRotationAuthenticatesBeforeReplacingStoredCredentials(): void
    {
        $repository = new WordPressSettingsRepository('mailer_review_rotation');
        $repository->save(MailerSettings::fromArray(['connection'=>['password'=>'rotation-canary']]));
        $before = get_option('mailer_review_rotation');
        try {
            $repository->rotateSecrets(new \SymPress\Mailer\Secret\SecretCipher(str_repeat('wrong-key', 4)), new \SymPress\Mailer\Secret\SecretCipher(str_repeat('new-key', 5)));
            self::fail('Wrong key must fail.');
        } catch (\RuntimeException) {
            self::assertSame($before, get_option('mailer_review_rotation'));
        }
        $replacement = new \SymPress\Mailer\Secret\SecretCipher(str_repeat('new-key', 5));
        $repository->rotateSecrets(new \SymPress\Mailer\Secret\SecretCipher(), $replacement);
        $after = get_option('mailer_review_rotation');
        self::assertSame('rotation-canary', $replacement->decrypt($after['connections']['primary']['password']));
        self::assertStringNotContainsString('rotation-canary', serialize($after));
        $this->expectException(\RuntimeException::class);
        $repository->get();
    }

    public function testNetworkAdminPostUsesAuthorizedExplicitScope(): void
    {
        $GLOBALS['current_screen'] = \WP_Screen::get('dashboard');
        $_POST = ['action'=>'sympress_mailer_save_settings','_sympress_scope'=>'network'];
        self::assertFalse(is_network_admin());
        self::assertTrue(SettingsScope::network());
        SettingsScope::assertCapability();
        $repository = new WordPressSettingsRepository('mailer_review_network');
        $repository->save(MailerSettings::fromArray(['connection'=>['password'=>'network-canary']]));
        self::assertFalse(get_option('mailer_review_network', false));
        self::assertStringStartsWith('enc:v2:', get_site_option('mailer_review_network')['connections']['primary']['password']);
        self::assertSame('network-canary', $repository->get()->defaultConnection()->password);
        self::assertTrue(wp_verify_nonce(wp_create_nonce(SettingsScope::nonce('sympress_mailer_save_settings')), 'sympress_mailer_save_settings_network') !== false);
        $id = wp_insert_user(['user_login'=>'site-admin-review','user_email'=>'site-admin@example.test','user_pass'=>bin2hex(random_bytes(20)),'role'=>'administrator']);
        self::assertIsInt($id);
        wp_set_current_user($id);
        self::assertTrue(current_user_can('manage_options'));
        self::assertFalse(current_user_can('manage_network_options'));
        $this->expectException(\RuntimeException::class);
        $repository->get();
    }

    public function testRuntimeInheritsNetworkDefaultsAndMigratesThemWithoutCreatingSiteOption(): void
    {
        update_site_option('mailer_review_inherited', ['connection' => ['provider' => 'smtp', 'host' => 'network.example.test', 'password' => 'network-runtime-canary']]);
        set_current_screen('front');
        wp_set_current_user(0);
        $repository = new WordPressSettingsRepository('mailer_review_inherited');
        self::assertSame('network.example.test', $repository->get()->defaultConnection()->host);
        self::assertSame('network-runtime-canary', $repository->get()->defaultConnection()->password);
        self::assertFalse(get_option('mailer_review_inherited', false));
        self::assertStringStartsWith('enc:v2:', get_site_option('mailer_review_inherited')['connection']['password']);
        update_option('mailer_review_inherited', ['connection' => ['host' => 'site.example.test']]);
        self::assertSame('site.example.test', $repository->get()->defaultConnection()->host);
        switch_to_blog(wp_insert_site(['domain' => DOMAIN_CURRENT_SITE, 'path' => '/inherit-review/']));
        try {
            self::assertSame('network.example.test', $repository->get()->defaultConnection()->host);
        } finally {
            restore_current_blog();
        }
    }

    public function testConnectionDestinationChangesNeverPersistPreviousSecrets(): void
    {
        $repository = new WordPressSettingsRepository('mailer_review_destination');
        $original = ['provider' => 'smtp', 'host' => 'old.example.test', 'password' => 'old-password-canary', 'api_key' => 'old-api-canary', 'dsn' => 'smtp://old-secret@old.example.test'];
        $repository->save(MailerSettings::fromArray(['connection' => $original]));
        foreach ([['host' => 'new.example.test'], ['provider' => 'postmark']] as $change) {
            $submitted = \SymPress\Mailer\Secret\SecretFields::preserve([...$original, ...$change, 'password' => '', 'api_key' => '', 'dsn' => ''], $original);
            $repository->save(MailerSettings::fromArray(['connection' => $submitted]));
            self::assertSame('', $repository->get()->defaultConnection()->password);
            self::assertSame('', $repository->get()->defaultConnection()->apiKey);
            self::assertSame('', $repository->get()->defaultConnection()->dsn);
            self::assertSame('', get_option('mailer_review_destination')['connections']['primary']['password']);
        }
        $fresh = \SymPress\Mailer\Secret\SecretFields::preserve(['provider' => 'smtp', 'host' => 'fresh.example.test', 'password' => 'new-password'], $original);
        self::assertSame('new-password', $fresh['password']);
        self::assertSame('', $fresh['api_key']);
    }

    public function testNetworkFormSavePersistsNetworkOptionAndReturnsToNetworkAdmin(): void
    {
        $GLOBALS['current_screen'] = \WP_Screen::get('dashboard');
        $_POST = ['action'=>'sympress_mailer_save_settings','_sympress_scope'=>'network','_tab'=>'misc','do_not_send'=>'1'];
        $_POST['_wpnonce'] = wp_create_nonce(SettingsScope::nonce('sympress_mailer_save_settings'));
        $_REQUEST = $_POST;
        $repository = new WordPressSettingsRepository('mailer_review_network_form');
        $admin = (new \ReflectionClass(AdminPage::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($admin, 'settingsRepository'))->setValue($admin, $repository);
        $redirect = static function(string $url): never {
            self::assertStringContainsString('/wp-admin/network/admin.php', $url);
            throw new \RuntimeException('review-redirect-intercepted');
        };
        add_filter('wp_redirect', $redirect);
        try { $admin->saveSettings();self::fail('Expected redirect.'); }
        catch (\RuntimeException $error) { self::assertSame('review-redirect-intercepted', $error->getMessage()); }
        finally { remove_filter('wp_redirect', $redirect); }
        self::assertTrue(get_site_option('mailer_review_network_form')['do_not_send']);
        self::assertFalse(get_option('mailer_review_network_form', false));
    }

    public function testFullConnectionFormNeverRendersStoredCredentials(): void
    {
        $admin = (new \ReflectionClass(AdminPage::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($admin, 'providers'))->setValue($admin, new \SymPress\Mailer\Provider\DefaultProviderRegistry());
        $settings = MailerSettings::fromArray(['connection'=>['provider'=>'smtp','password'=>'password-canary','dsn'=>'smtp://user:dsn-canary@example.test','api_key'=>'api-canary','username'=>'username-canary']]);
        ob_start();(new \ReflectionMethod($admin, 'generalTab'))->invoke($admin, $settings);$html=ob_get_clean();
        foreach (['password-canary','dsn-canary','api-canary','username-canary'] as $secret) { self::assertStringNotContainsString($secret, $html); }
        self::assertStringContainsString('_clear_secrets[dsn]', $html);
    }

    public function testSecretHtmlIsBlankAndHasIntentionalClear(): void
    {
        $admin = (new \ReflectionClass(AdminPage::class))->newInstanceWithoutConstructor();
        ob_start();
        (new \ReflectionMethod($admin, 'input'))->invoke($admin, 'password', 'SMTP password', 'html-canary', 'password');
        $html = ob_get_clean();
        self::assertStringNotContainsString('html-canary', $html);
        self::assertStringContainsString('value=""', $html);
        self::assertStringContainsString('_clear_secrets[password]', $html);
    }

    public function testWpMailUsesNativeFiltersAndSuccessEnvelope(): void
    {
        $repository = new WordPressSettingsRepository('mailer_review_delivery');
        $repository->save(MailerSettings::fromArray(['connection'=>['id'=>'primary','dsn'=>'null://null','from_email'=>'from@example.test']]));
        $transport = new class extends AbstractTransport {
            public ?SentMessage $sent = null;
            protected function doSend(SentMessage $message): void { $this->sent=$message; }
            public function __toString(): string { return 'review://local'; }
        };
        $secrets = new EnvironmentConnectionSecretResolver();
        $mailer = new MailerService($repository,new SymfonyEmailFactory(new NullEmailBodyProcessor(),new DefaultAttachmentPolicy()),new SymfonyMailerFactory(new DsnFactory($secrets),new ProviderApiTransportFactory($secrets),$transport));
        $bridge = new WordPressMailerBridge($repository,new WordPressMailParser(),$mailer);
        add_filter('pre_wp_mail',$bridge->send(...),2147483647,2);
        add_filter('wp_mail',static function(array $data): array { $data['message']="  filtered body  ";return $data; });
        add_filter('wp_mail_from',static fn ()=>'filtered@example.test');
        add_filter('wp_mail_from_name',static fn ()=>'Filtered name');
        add_filter('wp_mail_content_type',static fn ()=>'text/html');
        add_filter('wp_mail_charset',static fn ()=>'ISO-8859-1');
        $success = null;
        add_action('wp_mail_succeeded',static function(array $data) use (&$success): void { $success=$data; });
        self::assertTrue(wp_mail('to@example.test','Subject','original'));
        $email = $transport->sent->getOriginalMessage();
        self::assertSame('filtered@example.test',$email->getFrom()[0]->getAddress());
        self::assertSame('Filtered name',$email->getFrom()[0]->getName());
        self::assertSame('  filtered body  ',$email->getHtmlBody());
        self::assertSame('ISO-8859-1',$email->getHtmlCharset());
        self::assertSame(['to@example.test'],$success['to']);
        self::assertSame('  filtered body  ',$success['message']);
        self::assertArrayHasKey('attachments',$success);
        self::assertArrayHasKey('embeds',$success);
        self::assertFalse($email->getHeaders()->has('X-SymPress-Mailer-Source'));
    }

    public function testNativeHeaderEnvelopeAndEmbeddedImageDelivery(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mailer-embed-review-');
        file_put_contents($path, 'disposable embed content');
        try {
            $mail = (new WordPressMailParser())->parse(['to'=>'to@example.test', 'subject'=>'Review', 'message'=>'<img src="cid:logo">', 'headers'=>"Content-Type: text/html\nX-Review: custom\nCc: copy@example.test", 'embeds'=>['logo'=>$path]]);
            $factory = new SymfonyEmailFactory(new NullEmailBodyProcessor(), new DefaultAttachmentPolicy());
            $email = $factory->create($mail, new ConnectionConfig('primary', fromEmail:'from@example.test'), new MailerSettings(), 'review-id');
            self::assertCount(1, $email->getAttachments());
            self::assertStringContainsString('cid:' . $email->getAttachments()[0]->getContentId(), $email->getHtmlBody());
            self::assertStringContainsString('Content-ID:', $email->toString());
            self::assertSame(['logo'=>$path], WordPressMail::fromArray($mail->toArray())->embeds);
            $repository = new WordPressSettingsRepository('mailer_review_native_envelope');
            $sender = new class implements MailerInterface {
                public function send(WordPressMail $mail): SendResult { return SendResult::suppressed('local-fixture'); }
            };
            $bridge = new WordPressMailerBridge($repository, new WordPressMailParser(), $sender);
            $data=null;add_action('wp_mail_succeeded', static function(array $value) use (&$data): void { $data=$value; });
            $bridge->send(null,['to'=>'to@example.test','subject'=>'Review','message'=>'body','headers'=>"X-Review: custom\nCc: copy@example.test",'embeds'=>['logo'=>$path]]);
            self::assertSame(['X-Review'=>'custom'], $data['headers']);
            self::assertSame(['logo'=>$path], $data['embeds']);
        } finally { unlink($path); }
    }

    public function testOtherPluginInterceptionAndFailureEnvelopeArePreserved(): void
    {
        $repository = new WordPressSettingsRepository('mailer_review_failure');
        $mailer = new class implements MailerInterface {
            public int $calls=0;
            public function send(WordPressMail $mail): SendResult { ++$this->calls;return SendResult::failed('id','Local fixture failure.'); }
        };
        $bridge = new WordPressMailerBridge($repository,new WordPressMailParser(),$mailer);
        add_filter('pre_wp_mail',$bridge->send(...),2147483647,2);
        $foreign=static fn () => true;
        add_filter('pre_wp_mail',$foreign,10);
        self::assertTrue(wp_mail('to@example.test','Subject','body'));
        self::assertSame(0,$mailer->calls);
        remove_filter('pre_wp_mail',$foreign,10);
        $failure=null;
        add_action('wp_mail_failed',static function(\WP_Error $error) use (&$failure): void { $failure=$error; });
        self::assertFalse(wp_mail('to@example.test','Subject','body'));
        self::assertSame('wp_mail_failed',$failure->get_error_code());
        self::assertSame(['to@example.test'],$failure->get_error_data()['to']);
        self::assertSame('Subject',$failure->get_error_data()['subject']);
    }
}
