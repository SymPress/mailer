<?php

declare(strict_types=1);

namespace SymPress\Mailer\Secret;

/** Authenticated, versioned storage. Failures never return ciphertext as a credential. */
final readonly class SecretCipher
{
    public function __construct(private ?string $keyMaterial = null)
    {
    }

    public function encrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $key = $this->key();
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'sympress-mailer:v2');
        if (!is_string($encrypted)) {
            throw new \RuntimeException('Mailer secret encryption failed.');
        }
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Authenticated binary envelope for option and queue storage.
        return 'enc:v2:' . base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $key = $this->key();
        if (!str_starts_with($value, 'enc:')) {
            return $value; // Legacy plaintext is rewritten before the settings repository returns it.
        }
        if (str_starts_with($value, 'enc:v2:')) {
            $binary = $this->decode(substr($value, 7));
            if (strlen($binary) < 28) {
                throw new \RuntimeException('Invalid encrypted mailer secret.');
            }
            $plain = openssl_decrypt(substr($binary, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($binary, 0, 12), substr($binary, 12, 16), 'sympress-mailer:v2');
        } elseif (str_starts_with($value, 'enc:v1:')) {
            $parts = explode(':', $value, 5);
            if (count($parts) !== 5 || strlen($this->decode($parts[2])) !== 12 || strlen($this->decode($parts[3])) !== 16) {
                throw new \RuntimeException('Invalid encrypted mailer secret.');
            }
            $plain = openssl_decrypt($this->decode($parts[4]), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $this->decode($parts[2]), $this->decode($parts[3]));
        } else {
            throw new \RuntimeException('Unsupported encrypted mailer secret version.');
        }
        if (!is_string($plain)) {
            throw new \RuntimeException('Mailer secret cannot be decrypted; restore the encryption key or re-enter credentials.');
        }
        return $plain;
    }

    private function decode(string $value): string
    {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decode authenticated binary envelope, with strict validation.
        $decoded = base64_decode($value, true);
        if (!is_string($decoded)) {
            throw new \RuntimeException('Invalid encrypted mailer secret.');
        }
        return $decoded;
    }

    private function key(): string
    {
        $material = $this->keyMaterial;
        if ($material === null && defined('SYMPRESS_MAILER_ENCRYPTION_KEY')) {
            $material = (string) constant('SYMPRESS_MAILER_ENCRYPTION_KEY');
        }
        if ($material === null) {
            $material = '';
            foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $name) {
                if (!defined($name) || !is_string(constant($name)) || constant($name) === 'put your unique phrase here') {
                    continue;
                }

                $material .= constant($name);
            }
        }
        if (strlen($material) < 32 || !function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new \RuntimeException('Configure a private mailer encryption key of at least 32 bytes or valid WordPress salts.');
        }
        return hash('sha256', $material, true);
    }
}
