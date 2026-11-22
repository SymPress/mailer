# Hooks

This page lists every WordPress action and filter that SymPress Mailer fires or
applies, and the native WordPress mail hooks the `wp_mail()` bridge honours or
emits. Hook names and arguments are covered by the
[backward-compatibility promise](stability.md).

`$logId` identifies one delivery attempt. The default mailer generates a
32-character hexadecimal ID, sends it in the `X-SymPress-Mailer-Log-ID` header
and returns it in `SendResult::$logId`.

## Package Actions

### `sympress_mailer_delivery_failed`

```php
do_action('sympress_mailer_delivery_failed', string $logId, \Throwable $error, WordPressMail $mail, ConnectionConfig $connection);
```

Fired by the default `MailerInterface` implementation when building or sending
a mail throws; `$connection` is the default connection that was used. The
`wp_mail_failed` message stays generic, so this is the place to diagnose a
failure. Sensitive: the exception message can contain provider or server
response text, the mail contains recipients and the body, and the connection
carries the decrypted option credentials. Never log `$connection` and redact
the message (see `Support\FailureReason::redact()`) before showing it.

### `sympress_mailer_recipient_skipped`

```php
do_action('sympress_mailer_recipient_skipped', string $logId, string $field, string $address, WordPressMail $mail);
```

Fired by `SymfonyEmailFactory::create()` for each address Symfony Mime
rejects; the mail is sent to the remaining recipients. `$field` is `to`, `cc`,
`bcc` or `reply-to`; the configured forward addresses are added as `bcc`.
Sensitive: the address and the mail are personal data.

### `sympress_mailer_attachment_blocked`

```php
do_action('sympress_mailer_attachment_blocked', string $logId, string $attachment, string $reason, WordPressMail $mail, ConnectionConfig $connection);
```

Fired by `SymfonyEmailFactory::create()` for each attachment the
`AttachmentPolicyInterface` refuses; the mail is sent without it. `$reason`
comes from the policy's optional `rejectionReason(string $path): ?string`
method, otherwise it is `Attachment was blocked by policy.`. Embedded images
the policy refuses are skipped without this action. Sensitive: `$attachment`
is a server file path and `$connection` carries decrypted credentials.

## Package Filters

### `sympress_mailer_settings`

```php
apply_filters('sympress_mailer_settings', array $data): array
```

Applied by `WordPressSettingsRepository` every time it reads the
`sympress_mailer_settings` option (`get()` and `getForDelivery()`), after the
secrets are decrypted and before `MailerSettings::fromArray()`. A non-array
return value is ignored. It is not applied on `save()`, but the settings
screen saves what `get()` returned, so a filtered value can end up stored.
Sensitive: `$data` contains plaintext credentials.

### `sympress_mailer_connection_secret`

```php
apply_filters('sympress_mailer_connection_secret', null, ConnectionConfig $connection, string $field): mixed
```

Applied by `EnvironmentConnectionSecretResolver` only for connections whose
secret source is `env`, `wp_config` or `config`, once per field: `dsn`,
`host`, `username`, `password`, `api_key`, `api_secret`, `domain`, `region`,
`tenant_id`, `port`, `encryption`, `auto_tls` and `verify_peer`. A non-empty
scalar return value wins over the environment variables and constants. When a
credential comes from this source, the destination fields are read from it too
(see [Secret Storage and Upgrade](configuration.md#secret-storage-and-upgrade)).
Sensitive: the return value is a credential.

### `sympress_mailer_blocked_attachment_paths`

```php
apply_filters('sympress_mailer_blocked_attachment_paths', list<string> $paths): array
```

Applied by `DefaultAttachmentPolicy` to the list of blocked paths: `/etc`,
`/proc`, `/sys`, `/dev`, `/root`, `/var/log` and, when `ABSPATH` is defined,
`wp-config.php` in and above the WordPress root and `.htaccess` in it.
Non-string entries are dropped and a non-array return value is ignored.
`wp-config.php`, `.htaccess`, hidden files and directories, `auth.json`,
`debug.log` and SSH private keys stay blocked regardless. Sensitive: removing
entries lets mail attach server files.

## Native WordPress Hooks

The bridge listens to `pre_wp_mail`, which WordPress applies inside `wp_mail()`
after the `wp_mail` filter, so `wp_mail` filters keep working.

| Hook | Support |
| --- | --- |
| `pre_wp_mail` | Filter at priority `2147483647` with two arguments. An earlier non-null result is returned unchanged. Returns `null` (WordPress sends the mail itself) while the mailer is disabled or inside `MailerRuntimeGuard::withoutInterception()`; otherwise returns whether the mail was accepted. |
| `wp_mail_from`, `wp_mail_from_name` | Applied by `SymfonyEmailFactory::create()` after the From policy, so a filter can still change a forced sender. Defaults: `wordpress@` plus the network host without `www.`, and `WordPress`. |
| `wp_mail_content_type`, `wp_mail_charset` | Applied by `SymfonyEmailFactory::create()`. Defaults: the `Content-Type` header or `text/plain`, and its charset or the site charset. A content type containing `html` sends an HTML body with a generated text part. |
| `wp_mail_embed_args` | Applied per embedded image to `path`, `cid`, `name`, `type`, `disposition` and `encoding`. `path`, `cid`, `name` and `type` are used; the part is always inline. A non-array result, a missing path or a path the attachment policy refuses skips the image. |
| `wp_mail_succeeded` | Fired with WordPress' array (`to`, `subject`, `message`, `headers`, `attachments`, `embeds`) when the mail was accepted, including mail suppressed by "Do not send" or queued by an extension. |
| `wp_mail_failed` | Fired with a `WP_Error` (`wp_mail_failed`) and the same array when the mail was not accepted. The message is generic, for example `Mailer credentials are unavailable.` when the settings cannot be read. |
| `site_status_tests` | Filter that adds the direct test `sympress_mailer_delivery` to Tools > Site Health. |
| `phpmailer_init` | Not supported. PHPMailer is not used, so the action never fires; replace `MailerInterface` or `EmailBodyProcessorInterface` instead. |

`wp_mail_succeeded` and `wp_mail_failed` are fired only for mail that passes
through `wp_mail()`. The test email on the Tools screen and `mailer:test` call
`MailerInterface` directly, so they fire neither. With the default mailer,
`wp_mail_from` and the other filters applied by `SymfonyEmailFactory` still
apply to them.

The plugin also hooks `init`, `admin_menu`, `network_admin_menu` and its own
`admin_post_sympress_mailer_*` actions for translations and the admin screens.
Those registrations are internal.
