# SymPress Mailer

[![PHP: ^8.5](https://img.shields.io/badge/php-%5E8.5-777bb4.svg)](composer.json) [![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE) [![Security Policy](https://img.shields.io/badge/security-policy-2ea44f.svg)](SECURITY.md)

Core Symfony Mailer powered WordPress plugin for the SymPress kernel.

## Installation

Install the package through Composer in a SymPress WordPress project:

```sh
composer require sympress/mailer
```

The SymPress kernel discovers the `SymPress\Mailer\MailerBundle` bundle from
`composer.json` and loads `mailer/mailer.php` as the WordPress plugin entry.

## Feature Surface

- Replaces `wp_mail()` through the WordPress `pre_wp_mail` filter and honours
  the native `wp_mail_*` filters and actions (not `phpmailer_init`); see
  [Hooks](docs/hooks.md).
- Uses `symfony/mailer` transports and DSNs, including SMTP, sendmail, native
  mail, failover DSNs and optional Symfony provider bridges.
- Supports one primary connection, From email/name policy, return-path handling
  and TLS settings. The settings screen shows only the fields of the selected
  provider and reports validation errors in the form.
- Imports an existing connection from Fluent SMTP, WP Mail SMTP, Easy WP SMTP
  or Post SMTP.
- Tools screen with a test email that shows the failure reason, and an SMTP
  connection check that logs in without sending mail.
- Console commands `wp console mailer:test` and `wp console mailer:status`.
- A Site Health test that reports whether mail is delivered.
- Skips invalid recipients and malformed headers like WordPress core instead of
  losing the mail, and fires `sympress_mailer_delivery_failed` with the reason
  when delivery fails.
- Encrypts stored credentials with AES-256-GCM, or reads them from environment
  variables, constants or a filter.
- Multisite support: network settings that sites inherit for delivery until
  they save their own, and an uninstall that respects each site's and
  network's choice.
- Stores settings in the shared `sympress_mailer_settings` option. Its
  `MailerSettings` object also carries the feature state of SymPress Mailer
  Pro, such as routing rules, tracking, logging and alerts.
- Exposes interfaces such as `SymPress\Mailer\Application\MailerInterface` and
  `SymPress\Mailer\Message\EmailBodyProcessorInterface` as extension points.
  The public surface is stable since 1.0.0; see
  [Backward compatibility](docs/stability.md).

## Pro Extension

SymPress Mailer Pro is a separate commercial extension. It builds on this
package's extension points and replaces `MailerInterface` and
`EmailBodyProcessorInterface`.

## Provider Strategy

The core package depends on `symfony/mailer` and accepts any valid Symfony Mailer DSN. Dedicated providers such as SendGrid, Mailgun, Postmark, Brevo, Amazon SES, Gmail, Microsoft Graph, Mailjet, MailerSend, Mailtrap and Resend are enabled by installing the matching Symfony bridge listed in `composer.json` suggestions. Cloudflare Email, toSend, SMTP2GO, Netcore/Pepipost and Zoho ZeptoMail/TransMail use built-in API transports and need no bridge.

## Assets

Admin assets are built from `Resources/ts/` and `Resources/css/` with Symfony
Webpack Encore into `assets/`, which is not committed:

```sh
npm ci
npm run build
```

The PHP asset provider loads `assets/entrypoints.json`, or
`assets/manifest.json` when only that exists, and enqueues the assets on the
SymPress Mailer admin screens. Without a build it falls back to
`assets/admin.css` and `assets/admin.js`, which the package does not ship, so
the admin screens lack their styles and scripts until the assets are built.

## Documentation

- [Documentation index](docs/README.md)
- [Configuration](docs/configuration.md)
- [Hooks](docs/hooks.md)
- [Backward compatibility](docs/stability.md)
- [Development](docs/development.md)
- [1.0.0 release notes](docs/releases/1.0.0.md)

## Quality Checks

```sh
composer test
composer static-analysis
composer cs
npm run typecheck
```
