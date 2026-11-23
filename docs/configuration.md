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

With JavaScript enabled, the form shows only the fields the selected provider
uses, plus the DSN field whenever a DSN is stored. Hidden fields keep their
values and are saved unchanged. Every field that decides where mail and
credentials go stays visible: host and port for SMTP providers, and the host
for the built-in API providers, where it overrides the endpoint. Tick "Show all
fields" to reach the rest, for example the SMTP fallback of a bridge provider;
it is pre-ticked when the form comes back after a rejected save.

SMTP credentials are never sent in plaintext: a connection with a password
fails validation when neither SSL encryption nor Auto TLS is enabled.

For the built-in API providers (Cloudflare, ToSend, SMTP2GO, Pepipost,
ZeptoMail/TransMail), the host field overrides the API endpoint. Overrides must
use `https://` and resolve to a public address; loopback, private, link-local
and other reserved ranges are rejected on save and blocked again at send time,
including after redirects.

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

For SMTP connections, the Tools screen also offers a connection check. It
connects to the server, negotiates TLS and logs in with the saved credentials,
then disconnects without sending mail; it stops waiting after about 10 seconds.
It does not prove that mail is delivered or that the sender address is
accepted, and API, native, sendmail and failover connections cannot be checked
this way, so send a test email for those. The check never runs on save.

## Command Line

Two console commands run through the SymPress kernel's WP-CLI bridge, for
deploy smoke tests, CI and servers without admin access:

```sh
wp console mailer:test you@example.com
wp console mailer:status --probe
```

`mailer:test <to>` sends the Tools screen's test email. It prints the status:
`sent`, `suppressed` (Do not send is on, nothing was delivered) or `queued`
(accepted for later delivery; when an extension queued it after a failed
attempt, the reason of that attempt follows). A failed send prints the reason
with credentials replaced by `***`. Exit codes: `0` accepted, `1` failed, `2` invalid address
(nothing is sent).

`mailer:status` prints the delivery settings as seen at runtime: enabled, Do
not send, provider, secret source, From email and name, the force flags, and
the validation result. It never prints the DSN, username, password or API
credentials. `--probe` also runs the SMTP connection check described above.
Exit codes: `0` valid (and the probe passed or does not apply), `1` the
settings cannot be read (for example a missing encryption key), validation
failed or the probe failed. A disabled mailer is reported, not treated as an
error.

## Site Health

Tools > Site Health shows a "SymPress Mailer delivery" test, based on the
delivery settings as seen at runtime. The first matching state wins:

- **Critical:** the settings cannot be read, for example because the
  encryption key is missing or changed. Restore the key or re-enter the
  credentials.
- **Recommended:** the mailer is disabled, so WordPress uses its default mail
  function.
- **Critical** in the `production` environment type, **recommended**
  elsewhere: Do not send is on, so mail is accepted but not delivered.
- **Critical:** the default connection fails validation; the validation
  messages are listed.
- **Good:** mail is sent through the named provider.

The test only validates the configuration. It never connects to the mail
server and never shows the DSN, username, password or API credentials. It
stays active when Mailer Pro is installed.

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

When a password, API key, API secret or DSN is read from an external source
(`env`, `wp_config` or `config`), the destination is bound to that source too:
`DSN`, `HOST`, `PORT`, `ENCRYPTION`, `AUTO_TLS` and `VERIFY_PEER` are read from
the same candidate names (for example `SYMPRESS_MAILER_PRIMARY_HOST`) and the
settings form values are ignored. Missing values fall back to the defaults
(`tls`, TLS checks on, port 587, or 465 for `ssl`) and to the provider's
built-in host or API endpoint. Providers without one, such as **Other SMTP**
and **Symfony DSN**, fail validation until the host or DSN is defined
externally.

Stored secrets are never rendered into input values or connection-library JSON.
When a save fails validation or the health check, nothing is saved and the form
returns to its tab with the messages and the submitted values, except the secret
fields (DSN, username, password, API key and secret, domain, tenant ID): these
are never kept between requests and must be entered again.
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

On uninstall, every site option in every network and every network option is
deleted exactly when its own "Delete Settings on Uninstall" flag is on; a site's
choice never overrides its network's choice or the other way round.

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
[Hooks](hooks.md) lists every hook the bridge honours or emits.
As in WordPress core, an invalid To, Cc, Bcc, Reply-To or forward address is
skipped and the mail is sent to the remaining recipients; it fails only when no
To, Cc or Bcc recipient remains. Each skipped address fires
`sympress_mailer_recipient_skipped` with the log ID, the field (`to`, `cc`,
`bcc` or `reply-to`), the address and the `WordPressMail`.

The `wp_mail_failed` error message is deliberately generic. To diagnose a
failed delivery, listen to `sympress_mailer_delivery_failed`, which receives the
log ID, the caught `Throwable`, the `WordPressMail` and the `ConnectionConfig`
used. The exception message may contain provider or server response text, such
as host names or addresses, so listeners must treat it as sensitive. The plugin
logs nothing itself; the Tools screen uses this action to show the reason for a
failed test email, with the connection's credentials replaced by `***`. A site
administrator whose site inherits the network option sees only the generic
failure notice, because the reason could reveal network settings they cannot view.

Admin strings use the `sympress-mailer` text domain; the POT template is in
`languages/`. Existing explicit body-retention choices are preserved; new
settings default to no diagnostic body retention.
