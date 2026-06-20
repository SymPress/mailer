<?php

declare(strict_types=1);

namespace SymPress\Mailer\Config;

use SymPress\Mailer\Support\WordPressArray;

final readonly class ConnectionConfig
{
    public function __construct(
        public string $id,
        public string $name = '',
        public string $provider = 'dsn',
        public string $dsn = '',
        public string $host = '',
        public int $port = 587,
        public string $username = '',
        public string $password = '',
        public string $encryption = 'tls',
        public string $apiKey = '',
        public string $apiSecret = '',
        public string $domain = '',
        public string $region = '',
        public string $tenantId = '',
        public string $fromEmail = '',
        public string $fromName = '',
        public bool $forceFrom = false,
        public bool $forceFromName = false,
        public bool $returnPath = false,
        public bool $autoTls = true,
        public bool $verifyPeer = true,
        public string $keyStore = 'option',
        public string $secretPrefix = '',
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, string $fallbackId = 'primary'): self
    {
        $id = WordPressArray::string($data['id'] ?? $fallbackId);

        return new self(
            id: $id !== '' ? self::slug($id) : $fallbackId,
            name: WordPressArray::string($data['name'] ?? ''),
            provider: self::slug(WordPressArray::string($data['provider'] ?? 'dsn')),
            dsn: WordPressArray::string($data['dsn'] ?? ''),
            host: WordPressArray::string($data['host'] ?? ''),
            port: max(1, WordPressArray::int($data['port'] ?? 587, 587)),
            username: WordPressArray::string($data['username'] ?? ''),
            password: WordPressArray::string($data['password'] ?? ''),
            encryption: self::slug(WordPressArray::string($data['encryption'] ?? 'tls')),
            apiKey: WordPressArray::string($data['api_key'] ?? $data['apiKey'] ?? ''),
            apiSecret: WordPressArray::string($data['api_secret'] ?? $data['apiSecret'] ?? ''),
            domain: WordPressArray::string($data['domain'] ?? ''),
            region: WordPressArray::string($data['region'] ?? ''),
            tenantId: WordPressArray::string($data['tenant_id'] ?? $data['tenantId'] ?? ''),
            fromEmail: WordPressArray::string($data['from_email'] ?? $data['fromEmail'] ?? ''),
            fromName: WordPressArray::string($data['from_name'] ?? $data['fromName'] ?? ''),
            forceFrom: WordPressArray::bool($data['force_from'] ?? $data['forceFrom'] ?? false),
            forceFromName: WordPressArray::bool($data['force_from_name'] ?? $data['forceFromName'] ?? false),
            returnPath: WordPressArray::bool($data['return_path'] ?? $data['returnPath'] ?? false),
            autoTls: WordPressArray::bool($data['auto_tls'] ?? $data['autoTls'] ?? true),
            verifyPeer: WordPressArray::bool($data['verify_peer'] ?? $data['verifyPeer'] ?? true),
            keyStore: self::keyStore(WordPressArray::string($data['key_store'] ?? $data['keyStore'] ?? 'option')),
            secretPrefix: self::secretPrefix(WordPressArray::string($data['secret_prefix'] ?? $data['secretPrefix'] ?? '')),
        );
    }

    /** @return array<string, scalar> */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'provider'        => $this->provider,
            'dsn'             => $this->dsn,
            'host'            => $this->host,
            'port'            => $this->port,
            'username'        => $this->username,
            'password'        => $this->password,
            'encryption'      => $this->encryption,
            'api_key'         => $this->apiKey,
            'api_secret'      => $this->apiSecret,
            'domain'          => $this->domain,
            'region'          => $this->region,
            'tenant_id'       => $this->tenantId,
            'from_email'      => $this->fromEmail,
            'from_name'       => $this->fromName,
            'force_from'      => $this->forceFrom,
            'force_from_name' => $this->forceFromName,
            'return_path'     => $this->returnPath,
            'auto_tls'        => $this->autoTls,
            'verify_peer'     => $this->verifyPeer,
            'key_store'       => $this->keyStore,
            'secret_prefix'   => $this->secretPrefix,
        ];
    }

    private static function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9_\-]+/', '-', $slug);

        return trim(is_string($slug) ? $slug : '', '-');
    }

    private static function keyStore(string $value): string
    {
        $store = self::slug($value) ?: 'option';

        return match ($store) {
            'encrypted-option', 'encryptedoption' => 'encrypted_option',
            'wp-config', 'wpconfig', 'constant', 'constants' => 'wp_config',
            'environment' => 'env',
            'kernel-config', 'kernelconfig', 'filter' => 'config',
            default => $store,
        };
    }

    private static function secretPrefix(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9_]+/', '_', $value);

        return trim(is_string($value) ? $value : '', '_');
    }
}
