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
