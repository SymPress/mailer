# Development

## Install Dependencies

```sh
composer install
npm install
```

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
