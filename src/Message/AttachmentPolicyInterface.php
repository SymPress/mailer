<?php

declare(strict_types=1);

namespace SymPress\Mailer\Message;

interface AttachmentPolicyInterface
{
    public function allowed(string $path): bool;
}
