# Contributing

Thanks for taking the time to improve SymPress Mailer.

## Local Setup

```bash
composer install
npm install
composer test
composer static-analysis
composer cs
npm run build # includes the TypeScript typecheck
```

The package uses PHP 8.5, Symfony Mailer, Symfony DependencyInjection, PHPUnit,
PHPStan, PHP CS Fixer, PHPCS with the SymPress coding standards, TypeScript, and
Webpack Encore.

## Pull Requests

- Keep pull requests focused on one behavior or documentation change.
- Add or update tests for parser, settings, transport, or mail-delivery changes.
- Run the available checks before opening a pull request.
- Use Conventional Commits for commit messages, for example
  `feat(mailer): add provider configuration`.

## Coding Guidelines

- Keep WordPress hook interception small and delegate delivery to services.
- Preserve Symfony Mailer DSN behavior instead of duplicating transport logic.
- Treat stored settings as shared extension state for the Pro package.
- Avoid logging or exposing SMTP credentials, API keys, and message bodies unless
  the feature explicitly requires it.
