# Development

The real WordPress suite creates and drops its own unique disposable schema.
`SYMPRESS_MAILER_TEST_DB_HOST`, `SYMPRESS_MAILER_TEST_DB_USER` and
`SYMPRESS_MAILER_TEST_DB_PASSWORD` optionally select an isolated MariaDB or MySQL
server (the local default is `127.0.0.1:33079`, root, empty password).

## Install Dependencies

```sh
composer install
npm ci
```

Use Node.js 24. Admin tooling uses Encore 7, Babel 8 and Webpack CLI 6 with the
committed npm lock. The previous Yarn lock is replaced so local builds, CI and
audits use the same dependency graph.

## Build Admin Assets

```sh
npm run build
```

The package keeps fallback assets in `assets/admin.css` and `assets/admin.js`.
When Webpack Encore writes `assets/entrypoints.json`, the asset provider can load
the compiled entrypoints instead.

## Run Checks

```sh
composer test
composer static-analysis
composer cs
npm run typecheck
```

Use `composer tests` for the unit test suite only.

## Native Provider Recording

`composer tests:recording` exercises ToSend and SMTP2GO with Symfony's native
`RecorderHttpClient` and an explicit `RecorderConfiguration`. It records synthetic
responses from `MockHttpClient` to a private temporary HAR file, then replays the
same provider request. The inner replay client throws if called; a missing entry
must raise `HarEntryNotFoundException` without reaching it. No email or provider
network request is sent. Existing unit payload and provider-error tests still run
through `composer tests`.

The optional suite requires HttpClient 8.2's recorder API and fails on a skip when
that API is unavailable. Symfony 8.2 is not yet stable as of 2026-10-09; ordinary
QA continues to use the supported stable 8.1 dependencies. The package does not
require a development dependency or the PHPUnit Bridge. Once 8.2 is stable, its
native recording API is allowed by the existing `^8.1` constraint. The separate
suite has been qualified against the upstream 8.2 source; that does not certify a
stable 8.2 release. Upstream API changes before publication may require an update.

Source: [Symfony PR 63781](https://github.com/symfony/symfony/pull/63781).

## Extension Points

- Replace `SymPress\Mailer\Application\MailerInterface` to customize delivery.
- Replace `SymPress\Mailer\Message\EmailBodyProcessorInterface` to transform HTML
  message bodies before they are handed to Symfony Mailer.
- Read and write `MailerSettings` through `SettingsRepositoryInterface` when an
  extension needs to add package-specific settings.

## Real WordPress / Database Tests

The integration suite installs disposable WordPress from the Composer dev
requirement. It blocks WordPress HTTP requests and uses local transport doubles;
it never sends email or provider/SMS requests. PHP needs mysqli, OpenSSL and
pcntl (pcntl is required for Mailer Pro's overlapping worker test).

Start an isolated MariaDB service on `127.0.0.1:33079` with an empty root password,
then run:

```sh
SYMPRESS_MAILER_TEST_DB=sympress_review_mailer_unique_run composer tests:database
```

Use a new schema name matching `sympress_review_mailer[a-z0-9_]+` for each run.
The fixture refuses other names, creates a new database, and drops only that
owned database on shutdown. It never reuses an existing schema. Integration
fixtures are separate from the normal unit suite; CI runs both package QA and
the real WordPress integration workflow.
