# Localhost production-mode application — 28 October 2026

Branch: `codex/m2-local-production`, base `6e2b9f5` plus preserved uncommitted M2 work. User approved a separate empty production-partition database, named first Global Admin, production product wording and loopback-only operation. This is not hosted-release approval. Technical self-review is recorded below; Saturnino review, MFA and project-owner release acceptance remain separate.

## Delivered behavior

- Active runtime has APP_ENV=production, APP_DEBUG=false, TRAINING_ENVIRONMENT=production, password authentication and explicit TRAINING_LOCALHOST_PRODUCTION=true. Without the explicit profile, hosted production password access remains disabled. Middleware checks raw socket REMOTE_ADDR and loopback Host, ignoring forwarded-address claims. The launcher binds 127.0.0.1 only.
- Active database: `platform/.runtime/local-production.sqlite`. Migrated without seeders. At handoff verification it contained one ordinary Global Admin/immutable local identity, no clients, groups, enrolments, synthetic users or jobs. Password activation remained pending; no known password was assigned. User chooses the password privately in the setup page.
- Account links, eligibility, recovery lists and cancellation use the active environment. Production account creation defaults to non-synthetic, prohibits the HTTP classification field, and rejects synthetic service-level requests. Existing security, consent, current-password, audit, expiry, role and session-revocation requirements remain intact.
- Production UI has no test checkbox/classification labels, environment badge or local-development explanation. Empty platform provides Create first client. Email is not configured and AI generation is not configured are explicit states. Mock AI is excluded from production provider settings and rejected server-side, not silently substituted for an unavailable provider.
- The separate first-admin initializer is restricted to the designated SQLite database and production localhost profile. It requires an empty database for first execution, writes the user, identity, platform grant and audit marker in one transaction, and emits a one-hour single-use setup link. Reissue is allowed only for the exact same pending first account with its original sole active grant; activated or different accounts are refused. It cannot recover a lost activated Global Admin. Two simultaneous initializations yield one admin and one outstanding link.

## Runtime configuration and commands

Private ignored profile: `platform/.runtime/local-production-profile.json`. It contains its own application key, session cookie, database path, cache paths and storage root (`platform/.runtime/local-production-runtime/storage`). Existing `platform/.env` was not changed; the dedicated launcher loads overrides into its own process. Use the profile runner for commands against this database; bare artisan continues to use the earlier local configuration.

From `platform/`, start:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/local-production.ps1 -Serve
```

The launcher refuses an occupied port and never migrates or seeds on startup. Current launcher PID is saved in `.runtime/local-production-activation/launcher.pid` at repository root; verify its command and child listener before stopping anything. Port 8000 is left running. The disposable rehearsal on 8126 was stopped after checks.

To reissue the initial setup link before activation, run privately from platform:

```powershell
& ./scripts/local-production.ps1 -ArtisanArgs @('training:initialize-local-production','--name=Bernie Santos Cabase','--email=bernie@myvirtual.services','--operator=Bernie Santos Cabase','--reason=Reissue initial administrator setup')
```

Do not redirect this command to a file or capture its output in a transcript. It prints a secret link once and cancels the preceding setup link. After password activation it refuses. The actual setup URL was held only in memory and passed to the application browser panel; it is not in this handoff, audit events, flash storage, queued jobs or email.

Loopback HTTP uses an HttpOnly, SameSite=Strict profile-specific session cookie; Secure is false because this is HTTP on localhost. No workers or scheduler were started. SMTP, account email, live AI and mock authentication remain disabled; no live Cerebru adapter was introduced. Request-path output is suppressed and application log channel is null for this profile to avoid retaining setup URLs; database audit events remain active. Hosted TLS, monitoring, diagnostic logging/redaction, MFA and operations require separate release work.

## Verification and technical self-review

- Full focused run after the bootstrap race fix: **63 tests / 474 assertions passed**, including LocalProductionTest, LocalAccountsTest, ClientPermissionIntegrationTest, PlatformAdministrationTest, AiProviderTest and AiGenerationTest.
- Final follow-up after the production-only mock-AI guard: **27 tests / 171 assertions passed** across LocalProductionTest and both AI suites. Do not add these overlapping totals together. XML files are in the companion evidence directory.
- Command pattern from platform: `php -d extension=gd vendor/bin/phpunit --filter='LocalProductionTest|LocalAccountsTest|ClientPermissionIntegrationTest|PlatformAdministrationTest|AiProviderTest|AiGenerationTest'`. Tests used disposable SQLite files/in-memory databases and mocked provider HTTP, never the actual new administrator database.
- New checks cover bootstrap expiry/reissue/replay/activation denial, concurrent processes, one initial identity/grant, ordinary-account setup/login/reset/change/invitation, Member permissions, revoked sessions, no classification controls, production synthetic rejection, cross-environment identity exclusion, remote socket/Host rejection including forged forwarded address, disabled hosted mode, and mock-AI rejection.
- All migrations were rehearsed without seeders in `local-production-rehearsal.sqlite`, then applied to the empty actual database. Initial Windows cache-path errors were resolved using platform-relative isolated cache settings; no existing database was involved. Initial HTTP tests correctly encountered production CSRF enforcement; tests now submit real session tokens. Windows subprocess synchronization uses the project's established polling/flush pattern.
- Playwright against port 8126 passed empty-workspace rendering, first-client creation, ordinary-account setup, keyboard login, client selection and member denial from platform administration. 390px mobile screens had no horizontal overflow. Screenshots show the new empty-workspace and account form. The overview screenshot predates the final AI status wording update; automated tests passed after that update. Browser traces containing disposable private-link paths were removed; no real-password setup was automated.
- Actual runtime verification: production runtime/data environments, one user/identity, zero clients/groups/enrolments/jobs/synthetic users, pending password, Global Admin authority, mock/email/AI disabled, HTTP 200 login, loopback port 8000. No actual user password was selected or entered by the agent.
- Technical review: platform locking serializes initialization and reissue; random passwords never become active until setup; bootstrap marker and active grant prevent recovery bypass; purpose/target/issuer checks remain intact; environment-scoped cancellation closes a cross-partition gap; raw REMOTE_ADDR plus Host limits the approved profile to loopback; UI hiding is backed by service guards.
- No new schema migration required. MariaDB and hosted CI were not rerun for this SQLite-only runtime follow-up. Earlier evidence remains historical. Manual screen-reader review and Saturnino acceptance are not claimed.

## Preservation and rollback

Both pre-existing databases were hashed after stopping their verified old preview, before any code/runtime switch:

- `platform/database/database.sqlite`: `EE02294A6A20D7AD1BC03D92F5A5628843CBC10CEE22134936F26FEACD28BA33`
- `platform/.runtime/m2-browser.sqlite`: `A31ADA3F85F8E985B02E661DB5A5DA637F19D7C4365062815DB052C5986B3EA3`

Configuration backup: `.runtime/local-production-activation/previous.env`. Hash baseline: `.runtime/local-production-activation/baseline.json`. Existing databases were not migrated, copied into the new database, converted or deleted. All 29 previously recorded unrelated file hashes were checked for preservation.

Rollback: verify and stop only the new launcher and its PHP listener, then restart the previous `scripts/serve-m2-demo.ps1` from platform in a fresh shell. The old `.env` remains in place; restore the private backup only if it has subsequently changed. Keep the new database/profile/key/storage together for recovery. Do not reseed either database or use a broad Git reset. Port 8000 cannot host both profiles simultaneously.

The companion manifest.json identifies the exact delivered contents of the 20 application/configuration/test files changed in this slice. Private runtime files are excluded.
