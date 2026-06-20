<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Provider\DefaultProviderRegistry;
use SymPress\Mailer\Provider\ProviderCatalogInterface;
use SymPress\Mailer\Provider\ProviderDefinition;

final class ProviderRegistryTest extends TestCase
{
    public function testContainsCoreAndImportedProviderTargets(): void
    {
        $providers = (new DefaultProviderRegistry())->all();

        self::assertArrayHasKey('sendgrid', $providers);
        self::assertArrayHasKey('cloudflare', $providers);
        self::assertArrayHasKey('tosend', $providers);
        self::assertArrayHasKey('smtp2go', $providers);
        self::assertTrue($providers['cloudflare']->supports('custom_transport_required'));
        self::assertTrue($providers['cloudflare']->supports('custom_transport'));
    }

    public function testNormalizesProviderAliases(): void
    {
        $registry = new DefaultProviderRegistry();

        self::assertSame('ses', $registry->get('amazon-ses')?->key);
        self::assertSame('microsoftgraph', $registry->get('outlook')?->key);
        self::assertSame('brevo', $registry->get('sendinblue')?->key);
        self::assertSame('transmail', $registry->get('zeptomail')?->key);
    }

    public function testProviderDefaultsAndOptionsAreCentralized(): void
    {
        $registry = new DefaultProviderRegistry();
        $sendLayer = $registry->get('sendlayer');
        $smtp2go = $registry->get('smtp2go');
        $gmail = $registry->get('gmail');

        self::assertNotNull($sendLayer);
        self::assertSame(
            [
                'provider' => 'sendlayer',
                'host' => 'smtp.sendlayer.net',
                'port' => 587,
                'encryption' => 'tls',
            ],
            $sendLayer->applyDefaults(['provider' => 'sendlayer']),
        );
        self::assertSame(['username', 'password'], $sendLayer->requiredFields);
        self::assertSame('custom-api', $smtp2go?->transport);
        self::assertSame('global', $smtp2go?->defaults['region']);
        self::assertArrayHasKey('eu', $smtp2go?->options['region'] ?? []);
        self::assertSame('2GO', $smtp2go?->logo);
        self::assertTrue($gmail?->supports('oauth_state'));
    }

    public function testProviderDefaultsDoNotOverwriteExplicitValues(): void
    {
        $sendLayer = (new DefaultProviderRegistry())->get('sendlayer');

        self::assertSame(
            [
                'provider' => 'sendlayer',
                'host' => 'custom.smtp.test',
                'port' => '2525',
                'encryption' => 'ssl',
            ],
            $sendLayer?->applyDefaults(
                [
                    'provider' => 'sendlayer',
                    'host' => 'custom.smtp.test',
                    'port' => '2525',
                    'encryption' => 'ssl',
                ],
            ),
        );
    }

    public function testRegistryCanUseInjectedCatalog(): void
    {
        $registry = new DefaultProviderRegistry(
            new class implements ProviderCatalogInterface {
                public function all(): array
                {
                    return [
                        'example' => new ProviderDefinition('example', 'Example Mail', 'API'),
                    ];
                }
            },
        );

        self::assertSame('Example Mail', $registry->get('example')?->title);
        self::assertSame(['example' => 'Example Mail'], $registry->options());
    }
}
