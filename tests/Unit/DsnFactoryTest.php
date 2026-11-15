<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Transport\DsnFactory;

final class DsnFactoryTest extends TestCase
{
    public function testExplicitDsnWins(): void
    {
        $factory = new DsnFactory();

        $dsn = $factory->create(
            new ConnectionConfig(
                id: 'primary',
                dsn: 'failover(smtp://one.example smtp://two.example)',
                host: 'ignored.example',
            ),
        );

        self::assertSame('failover(smtp://one.example smtp://two.example)', $dsn);
    }

    public function testBuildsAuthenticatedSmtpDsn(): void
    {
        $factory = new DsnFactory();

        $dsn = $factory->create(
            new ConnectionConfig(
                id: 'primary',
                provider: 'smtp',
                host: 'smtp.example.test',
                port: 587,
                username: 'user@example.test',
                password: 'p@ss/word',
            ),
        );

        self::assertSame('smtp://user%40example.test:p%40ss%2Fword@smtp.example.test:587', $dsn);
    }

    public function testBuildsProviderApiDsn(): void
    {
        $factory = new DsnFactory();

        $dsn = $factory->create(
            new ConnectionConfig(
                id: 'primary',
                provider: 'mailgun',
                apiKey: 'key',
                domain: 'mg.example.test',
            ),
        );

        self::assertSame('mailgun+api://key:mg.example.test@default', $dsn);
    }

    public function testUsesSmtpProviderDefaultHost(): void
    {
        $factory = new DsnFactory();

        $dsn = $factory->create(
            new ConnectionConfig(
                id: 'primary',
                provider: 'sendlayer',
                username: 'user',
                password: 'secret',
            ),
        );

        self::assertSame('smtp://user:secret@smtp.sendlayer.net:587', $dsn);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function commandDsnProvider(): iterable
    {
        yield 'plain' => ['sendmail://default?command=/bin/sh%20-c%20id'];
        yield 'encoded key' => ['sendmail://default?comm%61nd=/bin/id'];
        yield 'second option' => ['sendmail://default?foo=1&command=/bin/id'];
        yield 'array key' => ['sendmail://default?command[]=/bin/id'];
        yield 'inside failover' => ['failover(smtp://one.example sendmail://default?command=/bin/id)'];
        yield 'parenthesis in query' => ['sendmail://default?x=(&command=/bin/id'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('commandDsnProvider')]
    public function testRejectsCommandOption(string $dsn): void
    {
        self::assertTrue(DsnFactory::hasCommandOption($dsn));

        $this->expectException(\InvalidArgumentException::class);
        (new DsnFactory())->create(new ConnectionConfig(id: 'primary', dsn: $dsn));
    }

    public function testAllowsDsnWithoutCommandOption(): void
    {
        self::assertFalse(DsnFactory::hasCommandOption('sendmail://default'));
        self::assertFalse(DsnFactory::hasCommandOption('roundrobin(smtp://a.example?verify_peer=0 smtp://b.example)?retry_period=15'));
    }
}
