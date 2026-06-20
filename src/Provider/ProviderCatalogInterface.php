<?php

declare(strict_types=1);

namespace SymPress\Mailer\Provider;

interface ProviderCatalogInterface
{
    /** @return array<string, ProviderDefinition> */
    public function all(): array;
}
