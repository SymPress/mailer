# Backward Compatibility

From 1.0.0 on, SymPress Mailer follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). The public surface
below changes incompatibly only in a new major version; patch and minor
releases keep it working. Class names are relative to `SymPress\Mailer\`.

## Stable

Breaking changes to these only happen in a major release:

- The hooks listed in [Hooks](hooks.md): names, arguments and argument types.
- The `sympress_mailer_settings` option structure as read by
  `MailerSettings::fromArray()` and `ConnectionConfig::fromArray()` and written
  by their `toArray()` methods, including the encrypted secret format:
  `enc:v2:` followed by base64 of a 12-byte IV, a 16-byte GCM tag and the
  AES-256-GCM ciphertext, keyed with the SHA-256 hash of
  `SYMPRESS_MAILER_ENCRYPTION_KEY` or of the WordPress keys and salts. The
  names of the external secret constants and environment variables
  (`<PREFIX>_<FIELD>`, where the prefix is the secret prefix or
  `SYMPRESS_MAILER_` plus the connection ID or provider) are part of this
  contract.
- The console commands `mailer:test <to>` and `mailer:status [--probe]`:
  names, arguments, options and exit codes, as described in
  [Command Line](configuration.md#command-line). The printed text may change.
- The extension-point interfaces and their service IDs in
  `Resources/config/services.yaml`, through which an extension replaces the
  default implementation:
  - `Application\MailerInterface`
  - `Config\SettingsRepositoryInterface`
  - `Config\DeliverySettingsRepositoryInterface`
  - `Message\AttachmentPolicyInterface`
  - `Message\EmailBodyProcessorInterface`
  - `Provider\ProviderCatalogInterface`
  - `Provider\ProviderRegistryInterface`
  - `Secret\ConnectionSecretResolverInterface`
  - `Validation\ConnectionHealthCheckerInterface`
  - `Validation\ConnectionValidatorInterface`
- The value objects, including their constructors and public properties:
  `Message\WordPressMail`, `Value\SendResult`, `Config\MailerSettings`,
  `Config\ConnectionConfig`, `Config\RateLimitConfig`,
  `Provider\ProviderDefinition`, `Validation\ConnectionHealth` and
  `Validation\ConnectionValidationResult`.
- The public methods of these services and helpers used by extensions:
  - `Message\SymfonyEmailFactory::create()`
  - `Transport\SymfonyMailerFactory`
  - `Application\TestEmailSender`
  - `Config\DeliverySettings`
  - `Secret\SecretCipher`, including its constructor, which takes the key
    material for secret rotation
  - `Secret\SecretFields`
  - `Config\SettingsScope`
  - `Support\MailerRuntimeGuard`
  - `Transport\RejectedDeliveryException`
  - `Support\Json`
  - `Support\WordPressArray`
  - `Support\FailureReason`

## Internal

These may change in any release:

- Everything under `Admin\`, `Asset\`, `Console\` (the classes; the
  command-line contract above stays stable), `Hook\` and `Import\`.
- The default implementations behind the interfaces, such as
  `Application\MailerService`, `Config\WordPressSettingsRepository` and the
  `Default*` classes. The one exception is
  `WordPressSettingsRepository::rotateSecrets()`, which the
  [key rotation procedure](configuration.md#secret-storage-and-upgrade) relies
  on.
- `Transport\DsnFactory`, `Transport\ProviderApiTransport` and
  `Transport\ProviderApiTransportFactory`.
- `Validation\SmtpConnectionProbe`.
- Admin HTML, CSS classes and `data-*` attributes, and the wording of messages.
- Constructor signatures of autowired services. Obtain services from the
  container instead of constructing them.

Anything not listed as stable is internal. All classes are final; build on the
interfaces instead of extending them.

## Not a Breaking Change

Adding optional constructor arguments, new methods on final classes, new hooks,
new settings keys with defaults and new providers is not a breaking change and
can happen in a minor release.
