# SymPress Mailer Documentation

SymPress Mailer replaces WordPress mail delivery with Symfony Mailer while
keeping configuration in the SymPress kernel service container.

## Guides

- [Configuration](configuration.md)
- [Development](development.md)

## Runtime Model

`WordPressMailerBridge` intercepts `wp_mail()` through the `pre_wp_mail` filter.
It parses the WordPress payload, applies the stored mailer settings, creates a
Symfony email message, and sends it through the configured transport.

The base package owns the shared `sympress_mailer_settings` option. Extension
packages, including SymPress Mailer Pro, reuse that option for advanced feature
state such as routing rules, tracking, logging, alerts, and queue settings.
