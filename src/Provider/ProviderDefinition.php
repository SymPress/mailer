<?php

declare(strict_types=1);

namespace SymPress\Mailer\Provider;

final readonly class ProviderDefinition
{
    private const array COMMON_FORM_FIELDS = ['key_store', 'secret_prefix', 'from_email', 'from_name', 'force_from', 'force_from_name', 'return_path'];
    private const array SMTP_FORM_FIELDS = ['host', 'port', 'encryption', 'auto_tls', 'verify_peer'];

    /**
     * @param list<string> $fields
     * @param list<string> $requiredFields
     * @param list<string> $secretFields
     * @param array<string, scalar> $defaults
     * @param array<string, array<string, string>> $options
     * @param array<string, bool> $capabilities
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $type,
        public string $transport = 'smtp',
        public array $fields = [],
        public array $requiredFields = [],
        public array $secretFields = [],
        public string $docsUrl = '',
        public string $logo = '',
        public array $defaults = [],
        public array $options = [],
        public array $capabilities = [],
    ) {
    }

    public function supports(string $capability): bool
    {
        return $this->capabilities[$capability] ?? false;
    }

    /**
     * Connection form fields that matter for this provider; the admin screen hides the others.
     *
     * @return list<string>
     */
    public function formFields(): array
    {
        // Every field that decides where mail and credentials go stays visible: a stale host from a
        // previously selected provider is still submitted and would otherwise redirect them unseen.
        $destinationFields = match (true) {
            $this->transport === 'smtp' => self::SMTP_FORM_FIELDS,
            $this->supports('custom_transport') => ['host'],
            default => [],
        };

        return array_values(array_unique([...self::COMMON_FORM_FIELDS, ...$this->fields, ...$destinationFields]));
    }

    /** @return array{0: string, 1: string, 2: string} */
    public function legacyTuple(): array
    {
        return [$this->key, $this->title, $this->type];
    }

    /**
     * @param array<string, mixed> $connection
     * @return array<string, mixed>
     */
    public function applyDefaults(array $connection): array
    {
        foreach ($this->defaults as $field => $default) {
            if (isset($connection[$field]) && (string) $connection[$field] !== '') {
                continue;
            }

            $connection[$field] = $default;
        }

        return $connection;
    }
}
