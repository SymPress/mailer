<?php

declare(strict_types=1);

namespace SymPress\Mailer\Transport;

use SymPress\Mailer\Config\ConnectionConfig;
use SymPress\Mailer\Secret\ConnectionSecretResolverInterface;

final class DsnFactory
{
    public function __construct(
        private ?ConnectionSecretResolverInterface $secretResolver = null,
    ) {
    }

    public function create(ConnectionConfig $connection): string
    {
        $connection = $this->secretResolver?->resolve($connection) ?? $connection;

        if ($connection->dsn !== '') {
            if (self::hasCommandOption($connection->dsn)) {
                throw new \InvalidArgumentException('Mail DSNs must not set the "command" option.');
            }

            return $connection->dsn;
        }

        return match ($connection->provider) {
            'sendmail' => 'sendmail://default',
            'native' => 'native://default',
            'sendgrid' => $this->singleKeyDsn('sendgrid+api', $connection),
            'brevo' => $this->singleKeyDsn('brevo+api', $connection),
            'postmark' => $this->singleKeyDsn('postmark+api', $connection),
            'resend' => $this->singleKeyDsn('resend+api', $connection),
            'mailersend' => $this->singleKeyDsn('mailersend+api', $connection),
            'mailtrap' => $this->singleKeyDsn('mailtrap+api', $connection),
            'mailgun' => $this->mailgunDsn($connection),
            'mailjet' => $this->keySecretDsn('mailjet+api', $connection),
            'ses', 'amazon-ses' => $this->sesDsn($connection),
            'gmail', 'google' => $this->gmailDsn($connection),
            'microsoftgraph', 'microsoft-graph', 'outlook', 'microsoft-365' => $this->microsoftGraphDsn($connection),
            default => $this->smtpDsn($connection),
        };
    }

    /**
     * Symfony's sendmail transport runs the "command" option via proc_open, so a DSN entered
     * in the admin UI would grant shell access. Mirrors Symfony's DSN split (space and ")")
     * and parse_str decoding, so encoded keys and failover()/roundrobin() wrappers are covered.
     */
    public static function hasCommandOption(#[\SensitiveParameter] string $dsn): bool
    {
        foreach (preg_split('/[ )]+/', $dsn) ?: [] as $part) {
            $queryStart = strpos($part, '?');

            if ($queryStart === false) {
                continue;
            }

            parse_str(substr($part, $queryStart + 1), $options);

            if (array_key_exists('command', $options)) {
                return true;
            }
        }

        return false;
    }

    private function smtpDsn(ConnectionConfig $connection): string
    {
        $scheme = $connection->encryption === 'ssl' || $connection->encryption === 'smtps'
            ? 'smtps'
            : 'smtp';
        $auth = '';

        if ($connection->username !== '') {
            $auth = rawurlencode($connection->username);

            if ($connection->password !== '') {
                $auth .= ':' . rawurlencode($connection->password);
            }

            $auth .= '@';
        }

        $host = $connection->host !== '' ? $connection->host : $this->defaultSmtpHost($connection->provider);
        $query = [];

        if (!$connection->autoTls) {
            $query['auto_tls'] = 'false';
        }

        if (!$connection->verifyPeer) {
            $query['verify_peer'] = '0';
        }

        $dsn = sprintf('%s://%s%s:%d', $scheme, $auth, $host, $connection->port);

        if ($query !== []) {
            $dsn .= '?' . http_build_query($query);
        }

        return $dsn;
    }

    private function singleKeyDsn(string $scheme, ConnectionConfig $connection): string
    {
        if ($connection->apiKey === '') {
            return $this->smtpDsn($connection);
        }

        return sprintf('%s://%s@default', $scheme, rawurlencode($connection->apiKey));
    }

    private function keySecretDsn(string $scheme, ConnectionConfig $connection): string
    {
        if ($connection->apiKey === '' || $connection->apiSecret === '') {
            return $this->smtpDsn($connection);
        }

        return sprintf(
            '%s://%s:%s@default',
            $scheme,
            rawurlencode($connection->apiKey),
            rawurlencode($connection->apiSecret),
        );
    }

    private function mailgunDsn(ConnectionConfig $connection): string
    {
        if ($connection->apiKey === '' || $connection->domain === '') {
            return $this->smtpDsn($connection);
        }

        return sprintf(
            'mailgun+api://%s:%s@default',
            rawurlencode($connection->apiKey),
            rawurlencode($connection->domain),
        );
    }

    private function sesDsn(ConnectionConfig $connection): string
    {
        if ($connection->apiKey === '' || $connection->apiSecret === '') {
            return $this->smtpDsn($connection);
        }

        $dsn = sprintf(
            'ses+api://%s:%s@default',
            rawurlencode($connection->apiKey),
            rawurlencode($connection->apiSecret),
        );

        if ($connection->region !== '') {
            $dsn .= '?' . http_build_query(['region' => $connection->region]);
        }

        return $dsn;
    }

    private function gmailDsn(ConnectionConfig $connection): string
    {
        if ($connection->username === '' || $connection->password === '') {
            return $this->smtpDsn($connection);
        }

        return sprintf(
            'gmail+smtp://%s:%s@default',
            rawurlencode($connection->username),
            rawurlencode($connection->password),
        );
    }

    private function microsoftGraphDsn(ConnectionConfig $connection): string
    {
        if ($connection->apiKey === '' || $connection->apiSecret === '' || $connection->tenantId === '') {
            return $this->smtpDsn($connection);
        }

        return sprintf(
            'microsoftgraph+api://%s:%s@default?%s',
            rawurlencode($connection->apiKey),
            rawurlencode($connection->apiSecret),
            http_build_query(['tenantId' => $connection->tenantId]),
        );
    }

    private function defaultSmtpHost(string $provider): string
    {
        return match ($provider) {
            'sendlayer' => 'smtp.sendlayer.net',
            'smtpcom' => 'send.smtp.com',
            'elasticemail' => 'smtp.elasticemail.com',
            'gmail', 'google' => 'smtp.gmail.com',
            'mandrill' => 'smtp.mandrillapp.com',
            'smtp2go' => 'mail.smtp2go.com',
            'sparkpost' => 'smtp.sparkpostmail.com',
            'zoho' => 'smtp.zoho.com',
            default => 'localhost',
        };
    }
}
