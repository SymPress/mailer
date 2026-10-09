# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
where applicable.

## 0.1.5 — 2026-10-09

- Add a separate optional native HTTP record/replay suite for synthetic ToSend and SMTP2GO exchanges, including missing-record and network-fallback checks. Existing payload and error unit tests remain in normal QA.
- Keep stable Symfony 8.1 dependencies; the native recorder suite requires the forthcoming 8.2 API and reports its absence explicitly.

## 0.1.4 — 2026-10-06

- Request synchronous SMTP2GO processing and validate recipient acceptance counts.
- Reject an HTTP 200 response with no accepted recipients; preserve ambiguous or partial outcomes for reconciliation rather than reporting success.
- Prefer SMTP2GO's message identifier over its request identifier.

## 0.1.3 — 2026-10-03

- Reserve the internal diagnostic header case-insensitively so caller-supplied
  headers cannot spoof or duplicate the durable message log ID.
- Update the TypeScript loader to remove high build-tool advisories.

## 0.1.2 — 2026-10-02

- Runtime delivery inherits network configuration when the site has no override. Changing provider or host clears preserved credentials. The production asset toolchain is rebuilt with a peer-compatible dependency graph and zero npm audit findings.

- Encrypt option credentials by default, migrate legacy values with conditional writes, and fail closed on missing/rotated keys.
- Hide secrets in admin forms, preserve blank fields, add explicit clearing and authorized network scope.
- Preserve native mail filters/actions, header envelopes and embedded images; remove source headers and add translation templates.
- Add real WordPress/Multisite integration tests and CI; default new settings to no diagnostic body retention.

- Initial SymPress Mailer package documentation.
