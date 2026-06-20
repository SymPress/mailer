<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Transport\ProviderApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mime\Email;

final class ProviderApiTransportTest extends TestCase
{
    public function testSendsToSendPayloadThroughApi(): void
    {
        $requests = [];
        $transport = new ProviderApiTransport(
            new ConnectionConfig(id: 'primary', provider: 'tosend', apiKey: 'test-key'),
            new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
                $requests[] = [$method, $url, $options];

                return new MockResponse('{"message_id":"msg_123"}', ['http_code' => 200]);
            }),
        );

        $message = (new Email())
            ->from('Team <team@example.test>')
            ->to('Ada <ada@example.test>')
            ->subject('Welcome')
            ->html('<p>Hello</p>')
            ->text('Hello');

        $sent = $transport->send($message);

        self::assertNotNull($sent);
        self::assertSame('msg_123', $sent->getMessageId());
        self::assertSame('POST', $requests[0][0]);
        self::assertSame('https://api.tosend.com/v2/emails', $requests[0][1]);
        self::assertSame(['Authorization: Bearer test-key'], $requests[0][2]['normalized_headers']['authorization']);

        $payload = json_decode((string) $requests[0][2]['body'], true);
        self::assertIsArray($payload);
        self::assertSame('team@example.test', $payload['from']['email']);
        self::assertSame('ada@example.test', $payload['to'][0]['email']);
        self::assertArrayNotHasKey('cc', $payload);
    }

    public function testSendsSmtp2goPayloadThroughRegionalApi(): void
    {
        $requests = [];
        $transport = new ProviderApiTransport(
            new ConnectionConfig(id: 'primary', provider: 'smtp2go', apiKey: 'smtp2go-key', region: 'eu'),
            new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
                $requests[] = [$method, $url, $options];

                return new MockResponse('{"data":{"email_id":"email-123"}}', ['http_code' => 200]);
            }),
        );

        $message = (new Email())
            ->from('team@example.test')
            ->to('ops@example.test')
            ->subject('Status')
            ->text('OK');

        $sent = $transport->send($message);

        self::assertNotNull($sent);
        self::assertSame('email-123', $sent->getMessageId());
        self::assertSame('https://eu-api.smtp2go.com/v3/email/send', $requests[0][1]);
        self::assertSame(['X-Smtp2go-Api-Key: smtp2go-key'], $requests[0][2]['normalized_headers']['x-smtp2go-api-key']);

        $payload = json_decode((string) $requests[0][2]['body'], true);
        self::assertIsArray($payload);
        self::assertSame(['ops@example.test'], $payload['to']);
        self::assertSame('OK', $payload['text_body']);
    }

    public function testRaisesProviderErrorMessageFromFailedApiResponse(): void
    {
        $transport = new ProviderApiTransport(
            new ConnectionConfig(id: 'primary', provider: 'tosend', apiKey: 'bad-key'),
            new MockHttpClient(static fn (): MockResponse => new MockResponse('{"error":"bad token"}', ['http_code' => 401])),
        );

        $message = (new Email())
            ->from('team@example.test')
            ->to('ops@example.test')
            ->subject('Status')
            ->text('OK');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('tosend API returned HTTP 401: bad token');

        $transport->send($message);
    }
}
