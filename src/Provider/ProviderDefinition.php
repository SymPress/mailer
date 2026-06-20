<?php

declare(strict_types=1);

namespace SymPress\Mailer\Provider;

final readonly class ProviderDefinition
{
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
