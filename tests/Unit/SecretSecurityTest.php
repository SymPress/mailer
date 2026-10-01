<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Secret\SecretCipher;
use SymPress\Mailer\Secret\SecretFields;
use SymPress\Mailer\Secret\EnvironmentConnectionSecretResolver;

final class SecretSecurityTest extends TestCase
{
    public function testAuthenticatedEncryptionPreservesPasswordWhitespace(): void
    {
        $cipher = new SecretCipher(str_repeat('a', 32));
        $secret = "  mailer-canary\t ";
        $encoded = $cipher->encrypt($secret);
        self::assertStringStartsWith('enc:v2:', $encoded);
        self::assertStringNotContainsString('mailer-canary', $encoded);
        self::assertNotSame($encoded, $cipher->encrypt($secret));
        self::assertSame($secret, $cipher->decrypt($encoded));
        self::assertSame($secret, ConnectionConfig::fromArray(['password' => $secret])->password);
    }

    public function testLegacyV1EnvelopeAuthenticatesAndUpgradesToV2(): void
    {
        $material=str_repeat('legacy-key',4);$iv=random_bytes(12);$tag='';
        $encrypted=openssl_encrypt('legacy-canary','aes-256-gcm',hash('sha256',$material,true),OPENSSL_RAW_DATA,$iv,$tag);
        $legacy='enc:v1:'.base64_encode($iv).':'.base64_encode($tag).':'.base64_encode($encrypted);
        $cipher=new SecretCipher($material);
        self::assertSame('legacy-canary',$cipher->decrypt($legacy));
        self::assertStringStartsWith('enc:v2:',$cipher->encrypt($cipher->decrypt($legacy)));
    }

    public function testWrongKeyNeverReturnsCiphertextAsCredential(): void
    {
        $encoded = (new SecretCipher(str_repeat('a', 32)))->encrypt('mailer-canary');
        $this->expectException(\RuntimeException::class);
        (new SecretCipher(str_repeat('b', 32)))->decrypt($encoded);
    }

    public function testMissingKeyFailsClosed(): void
    {
        $this->expectException(\RuntimeException::class);
        (new SecretCipher(''))->encrypt('mailer-canary');
    }

    public function testMalformedEnvelopeFailsClosed(): void
    {
        $this->expectException(\RuntimeException::class);
        (new SecretCipher(str_repeat('a', 32)))->decrypt('enc:v2:not base64');
    }

    public function testBlankPreservesAndExplicitClearDeletes(): void
    {
        $previous = ['password' => '  mailer-canary  ', 'api_key' => 'api-canary'];
        $saved = SecretFields::preserve(['password' => '', 'api_key' => 'replacement'], $previous);
        self::assertSame($previous['password'], $saved['password']);
        self::assertSame('replacement', $saved['api_key']);
        $cleared = SecretFields::preserve(['_clear_secrets' => ['password' => '1']], $previous);
        self::assertSame('', $cleared['password']);
        self::assertSame('api-canary', $cleared['api_key']);
        self::assertSame('connection[_clear_secrets][password]', SecretFields::clearName('connection[password]'));
    }

    public function testArbitraryConstantPrefixCannotReadWordPressAuthKey(): void
    {
        if (!defined('AUTH_KEY')) {
            define('AUTH_KEY', 'protected-site-auth-key');
        }
        $resolver = new EnvironmentConnectionSecretResolver();
        $connection = new ConnectionConfig(id: 'primary', keyStore: 'wp_config', secretPrefix: 'AUTH');
        self::assertNotContains('AUTH_KEY', $resolver->candidateNames($connection, 'key'));
        self::assertNotContains('AUTH_PASSWORD', $resolver->candidateNames($connection, 'password'));
        self::assertSame('', $resolver->resolve($connection)->password);
        self::assertContains('SYMPRESS_MAILER_PRIMARY_PASSWORD', $resolver->candidateNames($connection, 'password'));
    }
}
