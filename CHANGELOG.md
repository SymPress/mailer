# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
where applicable.

## 0.1.2 — 2026-10-02

- Runtime delivery inherits network configuration when the site has no override. Changing provider or host clears preserved credentials. The production asset toolchain is rebuilt with a peer-compatible dependency graph and zero npm audit findings.

- Encrypt option credentials by default, migrate legacy values with conditional writes, and fail closed on missing/rotated keys.
- Hide secrets in admin forms, preserve blank fields, add explicit clearing and authorized network scope.
- Preserve native mail filters/actions, header envelopes and embedded images; remove source headers and add translation templates.
- Add real WordPress/Multisite integration tests and CI; default new settings to no diagnostic body retention.

- Initial SymPress Mailer package documentation.
