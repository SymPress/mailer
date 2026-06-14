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

- Replaces `wp_mail()` through the WordPress `pre_wp_mail` filter.
- Uses `symfony/mailer` transports and DSNs, including SMTP, sendmail, native mail, failover DSNs and optional Symfony provider bridges.
- Supports one primary connection, From email/name policy, return-path handling, TLS settings and test email delivery.
- Stores settings in the shared `sympress_mailer_settings` option so extension packages can add their own feature state.
- Exposes `SymPress\Mailer\Application\MailerInterface` and `SymPress\Mailer\Message\EmailBodyProcessorInterface` as extension points.

## Pro Extension

`sympress/mailer-pro` lives in `packages/mailer-pro` and builds on this package like a premium extension. The Pro package replaces `MailerInterface` with a logging/routing/queue-aware implementation and replaces the body processor with open/click tracking.

## Provider Strategy

The core package depends on `symfony/mailer` and accepts any valid Symfony Mailer DSN. Dedicated providers such as SendGrid, Mailgun, Postmark, Brevo, Amazon SES, Gmail, Microsoft Graph, Mailjet, MailerSend, Mailtrap and Resend are enabled by installing the matching Symfony bridge listed in `composer.json` suggestions.

## Assets

Admin assets are built with Symfony Webpack Encore:

```sh
npm install
npm run build
```

The PHP asset provider loads `assets/entrypoints.json` when it exists and falls back to the legacy `assets/admin.css` and `assets/admin.js` files until a build is available.

## Documentation

- [Documentation index](docs/README.md)
- [Configuration](docs/configuration.md)
- [Development](docs/development.md)

## Quality Checks

```sh
composer test
composer cs:analyze
composer cs
```
