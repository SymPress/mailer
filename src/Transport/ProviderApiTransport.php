<?php

declare(strict_types=1);

namespace SymPress\Mailer\Transport;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Support\Json;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ProviderApiTransport extends AbstractApiTransport
{
    private const array PROVIDERS = ['cloudflare', 'tosend', 'smtp2go', 'pepipost', 'transmail', 'zeptomail'];

    public function __construct(
        private readonly ConnectionConfig $connection,
        ?HttpClientInterface $client = null,
    ) {

        parent::__construct($client);
    }

    public static function supportsProvider(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }

    public function __toString(): string
    {
        return $this->connection->provider . '+api://default';
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        [$endpoint, $headers, $payload] = match ($this->connection->provider) {
            'cloudflare' => $this->cloudflare($email, $envelope),
            'tosend' => $this->tosend($email),
            'smtp2go' => $this->smtp2go($email),
            'pepipost' => $this->netcore($email),
            'transmail', 'zeptomail' => $this->zeptomail($email),
            default => throw new \LogicException('Unsupported provider API transport.'),
        };
        $payload = $this->compactPayload($payload);

        if (!$this->client instanceof HttpClientInterface) {
            throw new \LogicException('Provider API transport requires an HTTP client.');
        }

        $response = $this->client->request(
            'POST',
            $endpoint,
            [
                'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json', ...$headers],
                'json'    => $payload,
            ],
        );

        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);
        $body = $this->responseBody($response, $content);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new HttpTransportException(
                sprintf('%s API returned HTTP %d: %s', $this->connection->provider, $statusCode, $this->errorMessage($body, $content)),
                $response,
            );
        }

        $messageId = $this->messageId($body);

        if ($messageId !== '') {
            $sentMessage->setMessageId($messageId);
        }

        return $response;
    }

    /** @return array{0: string, 1: array<string, string>, 2: array<string, mixed>} */
    private function cloudflare(Email $email, Envelope $envelope): array
    {
        return [
            $this->endpoint('https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($this->connection->tenantId) . '/email/sending/send'),
            ['Authorization' => 'Bearer ' . $this->connection->apiKey],
            [
                'to'          => $this->addresses($this->getRecipients($email, $envelope)),
                'from'        => $this->address($this->sender($email)),
                'subject'     => $email->getSubject() ?? '',
                'html'        => $this->html($email),
                'text'        => $this->text($email),
                'cc'          => $this->addresses($email->getCc()),
                'bcc'         => $this->addresses($email->getBcc()),
                'replyTo'     => $this->optionalAddress($email->getReplyTo()[0] ?? null),
                'headers'     => $this->customHeaders($email),
                'attachments' => $this->attachments($email, 'cloudflare'),
            ],
        ];
    }

    /** @return array{0: string, 1: array<string, string>, 2: array<string, mixed>} */
    private function tosend(Email $email): array
    {
        return [
            $this->endpoint('https://api.tosend.com/v2/emails'),
            ['Authorization' => 'Bearer ' . $this->connection->apiKey],
            [
                'from'        => $this->address($this->sender($email)),
                'to'          => $this->addresses($email->getTo()),
                'cc'          => $this->addresses($email->getCc()),
                'bcc'         => $this->addresses($email->getBcc()),
                'reply_to'    => $this->optionalAddress($email->getReplyTo()[0] ?? null),
                'subject'     => $email->getSubject() ?? '',
                'html'        => $this->html($email),
                'text'        => $this->text($email),
                'headers'     => $this->customHeaders($email),
                'attachments' => $this->attachments($email, 'tosend'),
            ],
        ];
    }

    /** @return array{0: string, 1: array<string, string>, 2: array<string, mixed>} */
    private function smtp2go(Email $email): array
    {
        return [
            $this->smtp2goEndpoint(),
            ['X-Smtp2go-Api-Key' => $this->connection->apiKey],
            [
                'sender'         => $this->formatAddress($this->sender($email)),
                'to'             => $this->formatAddresses($email->getTo()),
                'cc'             => $this->formatAddresses($email->getCc()),
                'bcc'            => $this->formatAddresses($email->getBcc()),
                'subject'        => $email->getSubject() ?? '',
                'html_body'      => $this->html($email),
                'text_body'      => $this->text($email),
                'custom_headers' => $this->smtp2goHeaders($email),
                'attachments'    => $this->attachments($email, 'smtp2go'),
            ],
        ];
    }

    /** @return array{0: string, 1: array<string, string>, 2: array<string, mixed>} */
    private function netcore(Email $email): array
    {
        return [
            $this->endpoint('https://emailapi.netcorecloud.net/v5/mail/send'),
            ['api_key' => $this->connection->apiKey],
            [
                'from'             => $this->address($this->sender($email)),
                'subject'          => $email->getSubject() ?? '',
                'content'          => $this->content($email),
                'personalizations' => [
                    [
                        'to'  => $this->addresses($email->getTo()),
                        'cc'  => $this->addresses($email->getCc()),
                        'bcc' => $this->addresses($email->getBcc()),
                    ],
                ],
                'attachments'      => $this->attachments($email, 'netcore'),
            ],
        ];
    }

    /** @return array{0: string, 1: array<string, string>, 2: array<string, mixed>} */
    private function zeptomail(Email $email): array
    {
        return [
            $this->endpoint('https://api.zeptomail.com/v1.1/email'),
            ['Authorization' => 'Zoho-enczapikey ' . $this->connection->apiKey],
            [
                'from'         => $this->zeptoAddress($this->sender($email)),
                'to'           => $this->zeptoAddresses($email->getTo()),
                'cc'           => $this->zeptoAddresses($email->getCc()),
                'bcc'          => $this->zeptoAddresses($email->getBcc()),
                'reply_to'     => array_values(array_map($this->zeptoAddress(...), $email->getReplyTo())),
                'subject'      => $email->getSubject() ?? '',
                'htmlbody'     => $this->html($email),
                'textbody'     => $this->text($email),
                'mime_headers' => $this->customHeaders($email),
                'attachments'  => $this->attachments($email, 'zeptomail'),
            ],
        ];
    }

    private function sender(Email $email): Address
    {
        return $email->getFrom()[0] ?? new Address('wordpress@localhost', 'WordPress');
    }

    /**
     * @param array<int, Address> $addresses
     * @return list<array{email: string, name?: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_values(array_map($this->address(...), $addresses));
    }

    /** @return array{email: string, name?: string} */
    private function address(Address $address): array
    {
        $data = ['email' => $address->getAddress()];

        if ($address->getName() !== '') {
            $data['name'] = $address->getName();
        }

        return $data;
    }

    /** @return array{email: string, name?: string}|null */
    private function optionalAddress(?Address $address): ?array
    {
        return $address !== null ? $this->address($address) : null;
    }

    /**
     * @param array<int, Address> $addresses
     * @return list<string>
     */
    private function formatAddresses(array $addresses): array
    {
        return array_values(array_map($this->formatAddress(...), $addresses));
    }

    private function formatAddress(Address $address): string
    {
        return $address->toString();
    }

    /** @return array{address: string, name?: string} */
    private function zeptoAddress(Address $address): array
    {
        $data = ['address' => $address->getAddress()];

        if ($address->getName() !== '') {
            $data['name'] = $address->getName();
        }

        return $data;
    }

    /**
     * @param array<int, Address> $addresses
     * @return list<array{email_address: array{address: string, name?: string}}>
     */
    private function zeptoAddresses(array $addresses): array
    {
        return array_values(array_map(
            fn (Address $address): array => ['email_address' => $this->zeptoAddress($address)],
            $addresses,
        ));
    }

    private function html(Email $email): ?string
    {
        $html = $email->getHtmlBody();

        return is_string($html) && $html !== '' ? $html : null;
    }

    private function text(Email $email): ?string
    {
        $text = $email->getTextBody();

        return is_string($text) && $text !== '' ? $text : null;
    }

    /** @return list<array{type: string, value: string}> */
    private function content(Email $email): array
    {
        $content = [];
        $html = $this->html($email);

        if ($html !== null) {
            $content[] = ['type' => 'html', 'value' => $html];
        }

        $text = $this->text($email);

        if ($text !== null) {
            $content[] = ['type' => 'text', 'value' => $text];
        }

        return $content;
    }

    /** @return array<string, string> */
    private function customHeaders(Email $email): array
    {
        $headers = [];

        foreach ($email->getHeaders()->all() as $header) {
            $name = $header->getName();

            if (in_array(strtolower($name), ['from', 'to', 'cc', 'bcc', 'reply-to', 'subject', 'content-type', 'mime-version'], true)) {
                continue;
            }

            $headers[$name] = $header->getBodyAsString();
        }

        return $headers;
    }

    /** @return list<array{name: string, value: string}> */
    private function smtp2goHeaders(Email $email): array
    {
        $headers = [];

        foreach ($this->customHeaders($email) as $name => $value) {
            $headers[] = ['name' => $name, 'value' => $value];
        }

        return $headers;
    }

    /** @return list<array<string, string>> */
    private function attachments(Email $email, string $provider): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            if (!$attachment instanceof DataPart) {
                continue;
            }

            $name = $attachment->getFilename() ?? 'attachment';
            $type = $attachment->getContentType();
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Provider JSON APIs require base64-encoded attachment bodies.
            $content = base64_encode($attachment->getBody());

            $attachments[] = match ($provider) {
                'smtp2go' => ['filename' => $name, 'fileblob' => $content, 'mimetype' => $type],
                'netcore' => ['name' => $name, 'content' => $content, 'type' => $type],
                'zeptomail' => ['name' => $name, 'content' => $content, 'mime_type' => $type],
                default => ['name' => $name, 'content' => $content, 'type' => $type],
            };
        }

        return $attachments;
    }

    private function endpoint(string $default): string
    {
        if ($this->connection->host === '') {
            return $default;
        }

        if (str_starts_with($this->connection->host, 'https://') || str_starts_with($this->connection->host, 'http://')) {
            return $this->connection->host;
        }

        return 'https://' . $this->connection->host;
    }

    private function smtp2goEndpoint(): string
    {
        $base = match ($this->connection->region) {
            'us' => 'https://us-api.smtp2go.com/v3/email/send',
            'eu' => 'https://eu-api.smtp2go.com/v3/email/send',
            'au' => 'https://au-api.smtp2go.com/v3/email/send',
            default => 'https://api.smtp2go.com/v3/email/send',
        };

        return $this->endpoint($base);
    }

    /** @return array<string, mixed> */
    private function responseBody(ResponseInterface $response, string $content): array
    {
        try {
            $body = $response->toArray(false);
        } catch (\Throwable) {
            $body = json_decode($content, true);
        }

        return is_array($body) ? $body : [];
    }

    /** @param array<string, mixed> $body */
    private function messageId(array $body): string
    {
        foreach (['message_id', 'messageId', 'request_id'] as $key) {
            if (is_scalar($body[$key] ?? null)) {
                return (string) $body[$key];
            }
        }

        if (is_array($body['data'] ?? null) && is_scalar($body['data']['email_id'] ?? null)) {
            return (string) $body['data']['email_id'];
        }

        return '';
    }

    /** @param array<string, mixed> $body */
    private function errorMessage(array $body, string $content): string
    {
        if ($body === []) {
            return $content !== '' ? $content : 'empty response';
        }

        foreach (['message', 'error', 'error_message'] as $key) {
            if (is_scalar($body[$key] ?? null)) {
                return (string) $body[$key];
            }
        }

        if (is_array($body['errors'] ?? null)) {
            return Json::encode($body['errors']);
        }

        if (is_array($body['data'] ?? null) && is_scalar($body['data']['error'] ?? null)) {
            return (string) $body['data']['error'];
        }

        return Json::encode($body);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function compactPayload(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = $this->isList($value)
                    ? array_values(array_filter(
                        array_map(fn (mixed $item): mixed => is_array($item) ? $this->compactPayload($item) : $item, $value),
                        static fn (mixed $item): bool => $item !== null && $item !== [],
                    ))
                    : $this->compactPayload($value);
            }

            if ($value === null || $value === []) {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /** @param array<mixed> $value */
    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
