# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
where applicable.

## 0.5.1 — 2026-11-23

- Changed: the admin screens were split into separate classes (Settings and Tools). No behaviour change.

## 0.5.0 — 2026-11-22

- New: Tools > Site Health shows whether mail is actually delivered. It reports a critical issue when the settings cannot be read (for example because the encryption key is missing or changed), when "Do not send" is on in a production environment or when the connection fails validation, with the validation messages listed. A disabled mailer and "Do not send" outside production are reported as recommended improvements. Otherwise the test passes and names the provider. The test only validates the configuration, never connects to the mail server and never shows the DSN, username, password or API credentials.

## 0.4.0 — 2026-11-22

- New: `wp console mailer:test you@example.com` sends the test email from the command line, for deploy smoke tests, CI and servers without admin access. It prints whether the mail was sent, suppressed by "Do not send" or queued, and on failure the reason with credentials replaced by `***`. It exits with 0 when the mail was accepted, 1 when it failed and 2 for an invalid address, in which case nothing is sent.
- New: `wp console mailer:status` shows the delivery configuration as seen at runtime (enabled, Do not send, provider, secret source, From address and force flags) and whether it is valid. `--probe` also runs the SMTP connection check. The DSN, username, password and API credentials are never printed. It exits with 1 when the settings cannot be read, for example because the encryption key is missing, when validation fails or when the probe fails.
- Dependencies: `symfony/console` is a direct requirement (already installed through `sympress/kernel`).

## 0.3.0 — 2026-11-22

- New: a "Check Connection" button on the Tools screen connects to the SMTP server, negotiates TLS and logs in with the saved credentials, then disconnects without sending mail. A wrong host, port, TLS mode or password now shows up without a test email. The check gives up after about 10 seconds, and credentials in the error message are replaced by `***`. API, native, sendmail and failover connections cannot be checked this way; the screen says so and suggests a test email instead.
- The check runs only on demand. Saving the settings still only validates the configuration, so an unreachable SMTP server never blocks saving.

## 0.2.0 — 2026-11-22

- New: the `sympress_mailer_delivery_failed` action fires when a mail cannot be delivered and receives the log ID, the caught exception, the `WordPressMail` and the connection. The exception may contain provider or server response text, so treat it as sensitive. The `wp_mail_failed` message stays generic.
- New: a failed test email on the Tools screen now shows the reason, for example "Test email failed: Connection could not be established with host …". Passwords, API keys, API secrets and DSNs are replaced by `***` before the reason is displayed.
- The test email notices are now translatable.

## 0.1.11 — 2026-11-22

- Fix: an invalid recipient address in To, Cc, Bcc, Reply-To or the forward addresses no longer makes the whole mail fail. As in WordPress core, the invalid address is skipped and the mail is sent to the remaining recipients; it still fails when no valid recipient is left. Each skipped address fires the new `sympress_mailer_recipient_skipped` action.

## 0.1.10 — 2026-11-22

- Fix: mails with a `Sender`, `Date` or `Return-Path` header in `wp_mail()` are delivered again instead of being lost. These headers are now applied as real sender, date and return path; a configured return path still takes precedence over the header, and a value that cannot be parsed is skipped.
- Fix: a custom header with a malformed name (for example an empty name or one containing spaces) is skipped instead of aborting the whole mail, as in WordPress core.

## 0.1.9 — 2026-11-16

- Security: API endpoint overrides for the built-in API providers (Cloudflare, ToSend, SMTP2GO, Pepipost, ZeptoMail/TransMail) must use `https://` and resolve to a public address. Loopback, private, link-local and reserved targets fail validation and are blocked again at send time, including after redirects and DNS changes.
- Security: SMTP connections with a password fail validation when neither SSL encryption nor Auto TLS is enabled, because the credentials would be sent in plaintext.
- Security: the attachment policy now also blocks hidden files and directories (`.env`, `.git`, `.ssh` …), `auth.json`, `debug.log`, SSH private keys and `/var/log`.
- Dependencies: `symfony/http-foundation` is a direct requirement (already installed through `sympress/kernel`); build-only `fast-uri` updated to 3.1.9.
- CI: pin `sympress/workflows` to v1.3.2.

## 0.1.8 — 2026-11-15

- Security: credentials from an external secret source (`env`, `wp_config`, `config`) are only sent to a destination from that same source. When a password, API key, API secret or DSN comes from there, the DSN, host, port, encryption, Auto TLS and Verify TLS Peer settings are read from it too and form values are ignored, so an administrator can no longer redirect the server-wide credentials to their own host. Missing values fall back to the provider's built-in host or API endpoint.
- **Behaviour change:** setups that keep the password or API key in the environment but the host, port or DSN in the settings form must now define them externally as well, for example `SYMPRESS_MAILER_PRIMARY_HOST` and `SYMPRESS_MAILER_PRIMARY_PORT`. Other SMTP and Symfony DSN connections fail validation until they do; named SMTP and API providers use their default host or endpoint.

## 0.1.7 — 2026-11-15

- Security: reject the sendmail `command` option in mail DSNs. Administrators could previously run shell commands through a crafted DSN; such DSNs now fail validation and are refused at send time.

## 0.1.6 — 2026-11-06

- Use the Symfony 8.1.11 security patches released on November 4.

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
