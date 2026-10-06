<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Transport\ProviderApiTransport;
use SymPress\Mailer\Transport\RejectedDeliveryException;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
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

                return new MockResponse('{"request_id":"request-123","data":{"email_id":"email-123","succeeded":1,"failed":0,"failures":[]}}', ['http_code' => 200]);
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

    public function testSmtp2goRejectsHttpSuccessWhenNoRecipientWasAccepted(): void
    {
        $transport = $this->smtp2goTransport('{"data":{"succeeded":0,"failed":1,"failures":["private rejection detail"]}}');
        $this->expectException(RejectedDeliveryException::class);
        $this->expectExceptionMessage('SMTP2GO rejected all 1 recipients.');
        $transport->send($this->email());
    }

    public function testPartialAcceptanceCannotBeRetriedAsACompleteRejection(): void
    {
        $transport = $this->smtp2goTransport('{"data":{"succeeded":1,"failed":1,"failures":["private detail"]}}');
        try {
            $transport->send($this->email()->addTo('other@example.test'));
            self::fail('Partial delivery must not be reported as sent.');
        } catch (TransportException $error) {
            self::assertNotInstanceOf(RejectedDeliveryException::class, $error);
            self::assertStringContainsString('reconciliation', $error->getMessage());
            self::assertStringNotContainsString('private detail', $error->getMessage());
        }
    }

    public function testMissingOrInconsistentAcceptanceCountsFailClosed(): void
    {
        foreach (['{}', '{"data":{"email_id":"id"}}', '{"data":{"succeeded":0,"failed":0}}', '{"data":{"succeeded":1,"failed":1}}'] as $body) {
            try {
                $this->smtp2goTransport($body)->send($this->email());
                self::fail('A response without complete acceptance evidence cannot be sent.');
            } catch (TransportException $error) {
                self::assertNotInstanceOf(RejectedDeliveryException::class, $error);
            }
        }
    }

    private function smtp2goTransport(string $body): ProviderApiTransport
    {
        return new ProviderApiTransport(
            new ConnectionConfig(id: 'primary', provider: 'smtp2go', apiKey: 'fixture-key'),
            new MockHttpClient(static fn (): MockResponse => new MockResponse($body, ['http_code' => 200])),
        );
    }

    private function email(): Email
    {
        return (new Email())->from('team@example.test')->to('ops@example.test')->subject('Status')->text('OK');
    }
}
