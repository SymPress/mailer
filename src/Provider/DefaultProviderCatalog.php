<?php

declare(strict_types=1);

namespace SymPress\Mailer\Provider;

final class DefaultProviderCatalog implements ProviderCatalogInterface
{
    private const array CUSTOM_API_CAPABILITIES = [
        'custom_transport_required' => true,
        'custom_transport'          => true,
    ];

    private const array SMTP_DEFAULTS = [
        'port'       => 587,
        'encryption' => 'tls',
    ];

    #[\Override]
    public function all(): array
    {
        $providers = [];

        foreach ($this->definitions() as $definition) {
            $providers[$definition->key] = $definition;
        }

        return $providers;
    }

    /** @return list<ProviderDefinition> */
    private function definitions(): array
    {
        return [
            new ProviderDefinition('native', 'Default (none)', 'PHP', 'native', logo: 'WP', capabilities: ['local_transport' => true]),
            new ProviderDefinition('sendmail', 'Sendmail', 'Local', 'sendmail', logo: 'SM', capabilities: ['local_transport' => true]),
            new ProviderDefinition('dsn', 'Symfony DSN', 'DSN', 'dsn', ['dsn'], ['dsn'], logo: 'DSN'),
            $this->smtp(),
            $this->smtpProvider('sendlayer', 'SendLayer', 'SL', 'smtp.sendlayer.net'),
            $this->smtpProvider('smtpcom', 'SMTP.com', 'SC', 'send.smtp.com'),
            $this->smtpProvider('elasticemail', 'Elastic Email', 'EE', 'smtp.elasticemail.com', ['api_key']),
            $this->smtp2go(),
            $this->smtpProvider('sparkpost', 'SparkPost', 'SP', 'smtp.sparkpostmail.com', ['api_key']),
            $this->smtpProvider('zoho', 'Zoho Mail', 'ZH', 'smtp.zoho.com'),
            $this->smtpProvider('mandrill', 'Mandrill', 'MD', 'smtp.mandrillapp.com'),
            $this->smtpProvider('gmail', 'Google / Gmail', 'G', 'smtp.gmail.com', capabilities: ['oauth_state' => true]),
            $this->bridgeProvider('sendgrid', 'SendGrid', 'SG'),
            $this->bridgeProvider('mailgun', 'Mailgun', 'MG', ['api_key', 'domain'], ['api_key', 'domain'], ['api_key']),
            $this->bridgeProvider('postmark', 'Postmark', 'PM'),
            $this->bridgeProvider('brevo', 'Brevo', 'BV'),
            $this->bridgeProvider('resend', 'Resend', 'RS'),
            $this->bridgeProvider(
                'ses',
                'Amazon SES',
                'SES',
                ['api_key', 'api_secret', 'region'],
                ['api_key', 'api_secret', 'region'],
                ['api_key', 'api_secret'],
            ),
            $this->bridgeProvider(
                'mailjet',
                'Mailjet',
                'MJ',
                ['api_key', 'api_secret'],
                ['api_key', 'api_secret'],
                ['api_key', 'api_secret'],
            ),
            $this->bridgeProvider('mailersend', 'MailerSend', 'MS'),
            $this->bridgeProvider('mailtrap', 'Mailtrap', 'MT'),
            $this->bridgeProvider(
                'microsoftgraph',
                '365 / Outlook',
                '365',
                ['api_key', 'api_secret', 'tenant_id'],
                ['api_key', 'api_secret', 'tenant_id'],
                ['api_key', 'api_secret'],
            ),
            $this->customApiProvider(
                'cloudflare',
                'Cloudflare Email',
                'CF',
                ['api_key', 'tenant_id'],
                ['api_key', 'tenant_id'],
                'https://developers.cloudflare.com/email-service/api/send-emails/rest-api/',
            ),
            $this->customApiProvider('tosend', 'toSend', 'TS', docsUrl: 'https://tosend.com/docs/api/send-email/'),
            $this->customApiProvider('pepipost', 'Netcore / Pepipost', 'NC', docsUrl: 'https://netcorecloud.com/email/email-api/'),
            $this->customApiProvider(
                'transmail',
                'Zoho ZeptoMail / TransMail',
                'ZM',
                docsUrl: 'https://www.zoho.com/zeptomail/help/api/email-sending.html',
            ),
        ];
    }

    private function smtp(): ProviderDefinition
    {
        return new ProviderDefinition(
            'smtp',
            'Other SMTP',
            'SMTP',
            'smtp',
            ['host', 'port', 'username', 'password'],
            ['host'],
            logo: 'SMTP',
            defaults: self::SMTP_DEFAULTS,
            options: ['encryption' => $this->encryptionOptions()],
        );
    }

    /**
     * @param list<string> $extraFields
     * @param array<string, bool> $capabilities
     */
    private function smtpProvider(
        string $key,
        string $title,
        string $logo,
        string $host,
        array $extraFields = [],
        array $capabilities = [],
    ): ProviderDefinition {

        $fields = array_values(array_unique([...['username', 'password'], ...$extraFields]));

        return new ProviderDefinition(
            $key,
            $title,
            'SMTP',
            'smtp',
            $fields,
            ['username', 'password'],
            $this->secretFields($fields),
            logo: $logo,
            defaults: ['host' => $host, ...self::SMTP_DEFAULTS],
            options: ['encryption' => $this->encryptionOptions()],
            capabilities: $capabilities,
        );
    }

    private function smtp2go(): ProviderDefinition
    {
        return $this->customApiProvider(
            'smtp2go',
            'SMTP2GO',
            '2GO',
            ['api_key', 'region'],
            ['api_key'],
            'https://developers.smtp2go.com/docs/send-an-email',
            ['region' => 'global'],
            ['region' => ['global' => 'Global', 'us' => 'US', 'eu' => 'EU', 'au' => 'Australia']],
        );
    }

    /**
     * @param list<string> $fields
     * @param list<string> $requiredFields
     * @param list<string> $secretFields
     */
    private function bridgeProvider(
        string $key,
        string $title,
        string $logo,
        array $fields = ['api_key'],
        array $requiredFields = ['api_key'],
        array $secretFields = ['api_key'],
    ): ProviderDefinition {

        return new ProviderDefinition(
            $key,
            $title,
            'API',
            'symfony-bridge',
            $fields,
            $requiredFields,
            $secretFields,
            logo: $logo,
        );
    }

    /**
     * @param list<string> $fields
     * @param list<string> $requiredFields
     * @param array<string, scalar> $defaults
     * @param array<string, array<string, string>> $options
     */
    private function customApiProvider(
        string $key,
        string $title,
        string $logo,
        array $fields = ['api_key'],
        array $requiredFields = ['api_key'],
        string $docsUrl = '',
        array $defaults = [],
        array $options = [],
    ): ProviderDefinition {

        return new ProviderDefinition(
            $key,
            $title,
            'API',
            'custom-api',
            $fields,
            $requiredFields,
            $this->secretFields($fields),
            $docsUrl,
            logo: $logo,
            defaults: $defaults,
            options: $options,
            capabilities: self::CUSTOM_API_CAPABILITIES,
        );
    }

    /**
     * @param list<string> $fields
     * @return list<string>
     */
    private function secretFields(array $fields): array
    {
        return array_values(array_intersect($fields, ['password', 'api_key', 'api_secret']));
    }

    /** @return array<string, string> */
    private function encryptionOptions(): array
    {
        return [
            'tls'  => 'TLS',
            'ssl'  => 'SSL',
            'none' => 'None',
        ];
    }
}
