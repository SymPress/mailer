<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\MailerSettings;
use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Hook\SiteHealthCheck;
use SymPress\Mailer\Provider\DefaultProviderRegistry;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;
use SymPress\Mailer\Validation\DefaultConnectionValidator;

final class SiteHealthCheckTest extends TestCase
{
    private const array VALID = ['connection' => ['provider' => 'smtp', 'host' => 'smtp.example.test', 'from_email' => 'site@example.test']];

    protected function tearDown(): void
    {
        unset($GLOBALS['sympress_mailer_test_environment']);
    }

    public function testRegisterAddsADirectTestAndKeepsExistingTests(): void
    {
        $check = $this->check(self::VALID);
        $tests = $check->register(['direct' => ['php_version' => ['label' => 'PHP']], 'async' => ['loopback' => ['label' => 'Loopback']]]);

        self::assertSame(['label' => 'PHP'], $tests['direct']['php_version']);
        self::assertSame(['label' => 'Loopback'], $tests['async']['loopback']);
        self::assertSame([$check, 'run'], $tests['direct']['sympress_mailer_delivery']['test']);
    }

    public function testUnreadableSettingsAreCriticalWithoutTheExceptionMessage(): void
    {
        $result = $this->check(new \RuntimeException('Configure a private mailer encryption key.'))->run();

        self::assertSame('critical', $result['status']);
        self::assertStringContainsString('cannot be decrypted', $result['description']);
        self::assertStringNotContainsString('private mailer encryption key', $result['description']);
        self::assertStringContainsString('href="https://example.test/wp-admin/admin.php?page=sympress-mailer"', $result['actions']);
        self::assertSame('sympress_mailer_delivery', $result['test']);
    }

    public function testDisabledMailerIsRecommended(): void
    {
        $result = $this->check(['enabled' => false] + self::VALID)->run();

        self::assertSame('recommended', $result['status']);
        self::assertStringContainsString('default mail function', $result['description']);
    }

    public function testDoNotSendIsCriticalInProduction(): void
    {
        $result = $this->check(['do_not_send' => true] + self::VALID)->run();

        self::assertSame('critical', $result['status']);
        self::assertStringContainsString('not delivered', $result['description']);
    }

    public function testDoNotSendIsRecommendedOutsideProduction(): void
    {
        $GLOBALS['sympress_mailer_test_environment'] = 'staging';

        self::assertSame('recommended', $this->check(['do_not_send' => true] + self::VALID)->run()['status']);
    }

    public function testInvalidConnectionIsCriticalAndListsTheEscapedErrors(): void
    {
        $result = $this->check(['connection' => ['provider' => 'smtp', 'host' => 'smtp.example.test', 'from_email' => '<b>bad</b>']])->run();

        self::assertSame('critical', $result['status']);
        self::assertStringContainsString('<li>from_email: Must be a valid email address.</li>', $result['description']);
        self::assertStringNotContainsString('<b>', $result['description']);
    }

    public function testValidConnectionIsGoodAndNamesTheProvider(): void
    {
        $result = $this->check(self::VALID)->run();

        self::assertSame('good', $result['status']);
        self::assertStringContainsString('Other SMTP', $result['description']);
        self::assertSame('', $result['actions']);
    }

    public function testNoSecretAppearsInAnyResult(): void
    {
        $secrets = ['username' => 'username-canary', 'password' => 'password-canary', 'api_key' => 'api-key-canary', 'api_secret' => 'api-secret-canary'];
        $results = [
            $this->check(['connection' => $secrets + self::VALID['connection']])->run(),
            $this->check(['connection' => $secrets + ['provider' => 'smtp', 'dsn' => 'smtp://dsn-user-canary:dsn-pass-canary@smtp.example.test?command=x']])->run(),
        ];
        self::assertSame(['good', 'critical'], array_column($results, 'status'));

        foreach ($results as $result) {
            $output = json_encode($result, JSON_THROW_ON_ERROR);
            foreach ([...array_values($secrets), 'dsn-user-canary', 'dsn-pass-canary'] as $secret) {
                self::assertStringNotContainsString($secret, $output);
            }
        }
    }

    /** @param array<string, mixed>|\Throwable $settings */
    private function check(array|\Throwable $settings): SiteHealthCheck
    {
        $settings = is_array($settings) ? MailerSettings::fromArray($settings) : $settings;
        $repository = new class($settings) implements SettingsRepositoryInterface {
            public function __construct(
                private MailerSettings|\Throwable $settings,
            ) {
            }

            public function get(): MailerSettings
            {
                return $this->settings instanceof \Throwable ? throw $this->settings : $this->settings;
            }

            public function save(MailerSettings $settings): void
            {
                $this->settings = $settings;
            }
        };
        $providers = new DefaultProviderRegistry();

        return new SiteHealthCheck($repository, new DefaultConnectionValidator($providers, new EnvironmentConnectionSecretResolver()), $providers);
    }
}
