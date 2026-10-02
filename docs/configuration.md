# Configuration

SymPress Mailer stores its settings in the `sympress_mailer_settings` WordPress
option. The admin UI writes the same structure that `MailerSettings` reads.

## Primary Connection

The primary connection supports:

- A raw Symfony Mailer DSN
- SMTP host, port, username, password, encryption, Auto TLS, and peer verification
- Native PHP mail and sendmail transports
- Provider shortcuts for SendGrid, Mailgun, Postmark, Brevo, Resend, Gmail,
  Amazon SES, Microsoft Graph, Mailjet, MailerSend, and Mailtrap
- Default From email/name, forced sender policy, and optional Return-Path handling

If a raw DSN is present, it wins over provider-specific fields.

## Provider Bridges

The package requires `symfony/mailer` and suggests the optional Symfony provider
bridges. Install the bridge that matches the provider before using provider API
DSNs, for example:

```sh
composer require symfony/sendgrid-mailer
```

SMTP fallback is used when a provider shortcut does not have the required API
credentials.

## Delivery Controls

Set `enabled` to `false` to let WordPress continue with its default mail flow.
Set `do_not_send` to `true` to accept intercepted mail without delivering it;
this is useful for local development and smoke tests.

Use the Tools screen to send a test email with the active connection.

## Secret Storage and Upgrade

Option credentials and credential-bearing DSNs use authenticated AES-256-GCM
`enc:v2` storage by default. OpenSSL is required. Configure a private
`SYMPRESS_MAILER_ENCRYPTION_KEY` containing at least 32 bytes, or retain valid
WordPress authentication keys/salts. Keep the key outside the database and
source control. Missing keys, malformed ciphertext and authentication failures
stop delivery; ciphertext is never treated as a password.

Existing plaintext `option` credentials and authenticated `enc:v1` envelopes
are migrated before being returned to callers. The migration conditionally
updates the exact old option value, so an administrator's concurrent save is
preserved. Migration/write failures stop the operation. Switching to external
secret sources removes abandoned option credentials. Environment/constants
prefixes must start with `SYMPRESS_MAILER_`; arbitrary WordPress constants cannot
be selected through the settings form.

Stored secrets are never rendered into input values or connection-library JSON.
A blank secret field preserves the old value. Use its **Clear stored secret**
checkbox to delete it. Password, API-key and DSN whitespace is significant and
preserved by the connection contract.

For rotation, enter maintenance mode, stop workers and drain Mailer Pro queues.
Use `WordPressSettingsRepository::rotateSecrets($previous, $replacement)` with
`SecretCipher` instances built from the old and replacement key material. The
method authenticates every existing secret before writing anything. When
rotating from the salt-derived key, the previous material is the concatenation
of AUTH_KEY, SECURE_AUTH_KEY, LOGGED_IN_KEY, NONCE_KEY, AUTH_SALT,
SECURE_AUTH_SALT, LOGGED_IN_SALT and NONCE_SALT in that order. After the operation,
replace the server key and resume workers. Do not change WordPress salts first:
restore the old key or re-enter credentials if authentication fails. Option
rotation does not re-encrypt queued delivery envelopes. If the old key is lost,
the existing admin form cannot decrypt settings. In maintenance mode, use a
trusted operator call to `SettingsRepositoryInterface::save()` with a complete
replacement configuration and new plaintext credentials; the repository encrypts
that replacement under the configured new key. Do not copy old ciphertext into
the replacement configuration.

## WordPress and Multisite Integration

Network admin forms carry an explicit network scope and scoped nonce through
`admin-post.php`. Network settings require `manage_network_options`; a site
administrator cannot select network scope. Saves return to the matching network
admin screen. Site settings and network settings remain distinct scopes. Runtime
delivery (frontend, cron, CLI and admin-triggered mail) inherits the network option when the current
site has no saved option; a saved site option is an explicit override. Admin
screens continue to edit their own scope. Inherited plaintext migration updates
the network option without creating a site override.

`WordPressSettingsRepository::getForDelivery()` provides that delivery view;
`get()` and `save()` retain the administrative scope. Custom repositories may
implement the optional `DeliverySettingsRepositoryInterface`; existing
`SettingsRepositoryInterface` implementations continue to work through `get()`.

Changing a connection's provider or SMTP host discards previously stored
connection credentials when the secret inputs are blank. Enter new credentials
for the new destination. Blank fields preserve credentials only while the
destination remains unchanged, including backup and additional connections.

The bridge preserves an earlier non-null `pre_wp_mail` result. It applies native
From, name, content-type and charset filters and emits `wp_mail_succeeded` or a
`wp_mail_failed` WP_Error with normalized native fields. Embedded-image inputs
and `wp_mail_embed_args` are carried into Symfony MIME parts. A success action
means accepted by the configured policy, including deliberately suppressed or
queued mail. It does not establish inbox receipt. PHPMailer-specific mutation
through `phpmailer_init` is not a Symfony transport extension point; use the
Mailer interfaces instead. Plugin-source headers are not emitted.

Admin strings use the `sympress-mailer` text domain; the POT template is in
`languages/`. Existing explicit body-retention choices are preserved; new
settings default to no diagnostic body retention.
