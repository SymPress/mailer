<?php

declare(strict_types=1);

namespace SymPress\Mailer\Validation;

final readonly class ConnectionValidationResult
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        public array $errors = [],
    ) {
    }

    public function valid(): bool
    {
        return $this->errors === [];
    }

    public function message(): string
    {
        $messages = [];

        foreach ($this->errors as $field => $errors) {
            foreach ($errors as $error) {
                $messages[] = $field . ': ' . $error;
            }
        }

        return implode("\n", $messages);
    }
}
