<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Provider\DefaultProviderRegistry;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;
use SymPress\Mailer\Transport\DsnFactory;
use SymPress\Mailer\Transport\ProviderApiTransport;
use SymPress\Mailer\Validation\DefaultConnectionValidator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mime\Email;

final class ExternalSecretDestinationTest extends TestCase
{
    /** @var list<string> */
    private array $envNames = [];

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->envNames as $name) {
            putenv($name);
        }

        $this->envNames = [];
    }

    public function testEnvironmentPasswordIsNotSentToFormHost(): void
    {
        $this->env(['SYMPRESS_MAILER_PRIMARY_PASSWORD' => 'env-pass']);
        $connection = $this->formConnection('smtp');

        $dsn = (new DsnFactory(new EnvironmentConnectionSecretResolver()))->create($connection);

        self::assertStringNotContainsString('attacker.example', $dsn);
        self::assertStringNotContainsString('auto_tls', $dsn);

        $result = $this->validator()->validate($connection);
        self::assertFalse($result->valid());
        self::assertArrayHasKey('host', $result->errors);
        self::assertStringContainsString('SYMPRESS_MAILER_PRIMARY_HOST', $result->message());
    }

    public function testEnvironmentPasswordUsesProviderDefaultHostInsteadOfFormHost(): void
    {
        $this->env(['SYMPRESS_MAILER_PRIMARY_PASSWORD' => 'env-pass']);

        $dsn = (new DsnFactory(new EnvironmentConnectionSecretResolver()))->create($this->formConnection('sendlayer'));

        self::assertSame('smtp://user:env-pass@smtp.sendlayer.net:587', $dsn);
    }

    public function testEnvironmentPasswordWithEnvironmentDestinationWorks(): void
    {
        $this->env([
            'SYMPRESS_MAILER_PRIMARY_PASSWORD' => 'env-pass',
            'SYMPRESS_MAILER_PRIMARY_HOST' => 'smtp.env.example',
            'SYMPRESS_MAILER_PRIMARY_PORT' => '2525',
            'SYMPRESS_MAILER_PRIMARY_ENCRYPTION' => 'ssl',
            'SYMPRESS_MAILER_PRIMARY_VERIFY_PEER' => 'false',
        ]);
        $connection = $this->formConnection('smtp');

        $dsn = (new DsnFactory(new EnvironmentConnectionSecretResolver()))->create($connection);

        self::assertSame('smtps://user:env-pass@smtp.env.example:2525?verify_peer=0', $dsn);
        self::assertTrue($this->validator()->validate($connection)->valid());
    }

    public function testEnvironmentApiKeyIsNotSentToFormEndpoint(): void
    {
        $this->env(['SYMPRESS_MAILER_TOSEND_API_KEY' => 'env-key']);
        $urls = [];
        $connection = (new EnvironmentConnectionSecretResolver())->resolve(
            new ConnectionConfig(id: 'primary', provider: 'tosend', host: 'http://attacker.example/collect', keyStore: 'env'),
        );
        $transport = new ProviderApiTransport(
            $connection,
            new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
                $urls[] = $url;

                return new MockResponse('{"message_id":"msg_1"}', ['http_code' => 200]);
            }),
        );

        $transport->send((new Email())->from('team@example.test')->to('ada@example.test')->subject('Hi')->text('Hi'));

        self::assertSame(['https://api.tosend.com/v2/emails'], $urls);
    }

    public function testFormDestinationIsKeptForOptionKeyStore(): void
    {
        $this->env(['SYMPRESS_MAILER_PRIMARY_PASSWORD' => 'env-pass']);
        $connection = new ConnectionConfig(
            id: 'primary',
            provider: 'smtp',
            host: 'smtp.form.example',
            port: 25,
            username: 'user',
            password: 'form-pass',
            encryption: 'none',
            autoTls: false,
        );

        $dsn = (new DsnFactory(new EnvironmentConnectionSecretResolver()))->create($connection);

        self::assertSame('smtp://user:form-pass@smtp.form.example:25?auto_tls=false', $dsn);
    }

    private function formConnection(string $provider): ConnectionConfig
    {
        return new ConnectionConfig(
            id: 'primary',
            provider: $provider,
            host: 'attacker.example',
            port: 25,
            username: 'user',
            encryption: 'none',
            autoTls: false,
            keyStore: 'env',
        );
    }

    /** @param array<string, string> $values */
    private function env(array $values): void
    {
        foreach ($values as $name => $value) {
            putenv($name . '=' . $value);
            $this->envNames[] = $name;
        }
    }

    private function validator(): DefaultConnectionValidator
    {
        return new DefaultConnectionValidator(new DefaultProviderRegistry(), new EnvironmentConnectionSecretResolver());
    }
}
