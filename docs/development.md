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
composer cs:analyze
composer cs
```

Use `composer test:unit` for the unit test suite only.

## Extension Points

- Replace `SymPress\Mailer\Application\MailerInterface` to customize delivery.
- Replace `SymPress\Mailer\Message\EmailBodyProcessorInterface` to transform HTML
  message bodies before they are handed to Symfony Mailer.
- Read and write `MailerSettings` through `SettingsRepositoryInterface` when an
  extension needs to add package-specific settings.
