<?php

declare(strict_types=1);

namespace SymPress\Mailer\Provider;

interface ProviderRegistryInterface
{
    /** @return array<string, ProviderDefinition> */
    public function all(): array;

    public function get(string $provider): ?ProviderDefinition;

    /** @return array<string, string> */
    public function options(): array;
}
