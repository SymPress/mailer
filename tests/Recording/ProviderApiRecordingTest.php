<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Recording;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Transport\ProviderApiTransport;
use Symfony\Component\HttpClient\Exception\HarEntryNotFoundException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Recorder\RecorderConfiguration;
use Symfony\Component\HttpClient\Recorder\RecorderMode;
use Symfony\Component\HttpClient\RecorderHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mime\Email;

final class ProviderApiRecordingTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        if (!class_exists(RecorderHttpClient::class) || !class_exists(RecorderConfiguration::class) || !enum_exists(RecorderMode::class)) {
            self::markTestSkipped('Native HTTP recording requires Symfony HttpClient 8.2; stable 8.1 remains supported.');
        }
        $this->directory = sys_get_temp_dir() . '/sympress-provider-recording-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        if (!isset($this->directory)) {
            return;
        }
        foreach (glob($this->directory . '/*.har') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    #[DataProvider('providers')]
    public function testSyntheticProviderExchangeRecordsAndReplaysWithoutFallback(string $provider, string $response, string $id): void
    {
        $file = $this->directory . '/provider.har';
        $record = new RecorderHttpClient(
            new MockHttpClient(static fn (): MockResponse => new MockResponse($response, ['http_code' => 200])),
            new RecorderConfiguration(RecorderMode::Record, $file),
        );
        $connection = new ConnectionConfig(id: 'fixture', provider: $provider, apiKey: 'synthetic-test-token');
        $message = $this->message();
        $sent = (new ProviderApiTransport($connection, $record))->send($message);
        self::assertNotNull($sent);
        self::assertSame($id, $sent->getMessageId());
        self::assertFileExists($file);

        $networkCalls = 0;
        $replay = new RecorderHttpClient(
            new MockHttpClient(static function () use (&$networkCalls): never {
                ++$networkCalls;
                throw new \LogicException('Replay must never fall through to a network transport.');
            }),
            new RecorderConfiguration(RecorderMode::Replay, $file),
        );
        $replayed = (new ProviderApiTransport($connection, $replay))->send($message);
        self::assertNotNull($replayed);
        self::assertSame($id, $replayed->getMessageId());
        self::assertSame(0, $networkCalls);

        try {
            $replay->request('POST', 'https://missing.example.test/email')->getStatusCode();
            self::fail('A missing replay must fail without contacting the inner client.');
        } catch (HarEntryNotFoundException) {
            self::assertSame(0, $networkCalls);
        }
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function providers(): iterable
    {
        yield 'ToSend' => ['tosend', '{"message_id":"fixture-123"}', 'fixture-123'];
        yield 'SMTP2GO' => ['smtp2go', '{"data":{"email_id":"fixture-456","succeeded":1,"failed":0,"failures":[]}}', 'fixture-456'];
    }

    private function message(): Email
    {
        $message = (new Email())->from('team@example.test')->to('ops@example.test')->subject('Synthetic fixture')->text('Test body');
        $message->getHeaders()->addIdHeader('Message-ID', 'recording-fixture@example.test');
        $message->getHeaders()->addDateHeader('Date', new \DateTimeImmutable('2026-10-09T00:00:00+00:00'));
        return $message;
    }
}
