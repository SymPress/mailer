# SymPress Mailer

Core WordPress mail interception and Symfony Mailer transport package. Start
with `docs/README.md`; inspect `Resources/config/services.yaml` before changing
an extension point.

## Key paths

- `src/Hook/WordPressMailerBridge.php`: thin `pre_wp_mail` boundary.
- `src/Message/`: WordPress input parsing and Symfony email creation.
- `src/Transport/`: DSN and provider transport behavior.
- `src/Config/`: shared settings contract consumed by Mailer Pro.
- `Resources/ts/`: admin source; generated assets are not source of truth.

## Verification

- Focused PHP: `vendor/bin/phpunit --configuration phpunit.xml.dist --filter <TestName>`
- TypeScript: `npm run typecheck`
- Asset build: `npm run build` (includes the typecheck)
- Full PHP: `composer qa`

## Invariants

- Keep hook interception small: parse once, delegate through `MailerInterface`.
- Preserve valid Symfony Mailer DSNs and provider bridge behavior.
- Never log or expose credentials. Treat message bodies as sensitive; Mailer Pro
  persists them only when its `saveBody` setting is enabled.
- `MailerRuntimeGuard::withoutInterception()` must restore state after failures.
- Keep public interfaces and `MailerSettings` backward-compatible with
  `sympress/mailer-pro`.

## Cross-repository impact

Mailer Pro replaces `MailerInterface` and `EmailBodyProcessorInterface` and
extends the shared settings object. Search that repository before changing
these contracts, service aliases, or serialized settings.

## Definition of done

The hook/transport regression test passes, `npm run typecheck` and
`composer qa` pass, docs use manifest-backed commands, and no credentials or
compiled assets are committed unintentionally.
