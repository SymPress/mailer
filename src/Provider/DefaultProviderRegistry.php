<?php

declare(strict_types=1);

namespace SymPress\Mailer\Provider;

final class DefaultProviderRegistry implements ProviderRegistryInterface
{
    /** @var array<string, ProviderDefinition>|null */
    private ?array $providers = null;

    public function __construct(
        private readonly ProviderCatalogInterface $catalog = new DefaultProviderCatalog(),
    ) {
    }

    #[\Override]
    public function all(): array
    {
        return $this->providers ??= $this->build();
    }

    #[\Override]
    public function get(string $provider): ?ProviderDefinition
    {
        $provider = $this->normalize($provider);

        return $this->all()[$provider] ?? null;
    }

    #[\Override]
    public function options(): array
    {
        $options = [];

        foreach ($this->all() as $definition) {
            $options[$definition->key] = $definition->title;
        }

        return $options;
    }

    /** @return array<string, ProviderDefinition> */
    private function build(): array
    {
        return $this->catalog->all();
    }

    private function normalize(string $provider): string
    {
        return match (strtolower(trim($provider))) {
            'amazon-ses', 'amazonses' => 'ses',
            'google' => 'gmail',
            'microsoft-graph', 'outlook', 'microsoft-365' => 'microsoftgraph',
            'sendinblue' => 'brevo',
            'zeptomail', 'zepto-mail' => 'transmail',
            default => strtolower(trim($provider)),
        };
    }
}
