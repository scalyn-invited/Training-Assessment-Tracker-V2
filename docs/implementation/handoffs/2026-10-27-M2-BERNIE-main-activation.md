# M2 main local app activation

Date: 27 October 2026. User explicitly approved bringing M2 into the main working copy, using a fresh synthetic database, and leaving the preview running. Saturnino refines the UI later; that review does not block local use.

Branch: `codex/m2-main-local`; base `6e2b9f5`; changes uncommitted. Verified all 52 isolated-delivery manifest entries before transferring. Preserved unrelated files. The earlier isolated-delivery manifest describes that source snapshot; this activation additionally changes test authentication configuration and adds the local preview launcher.

## Active app

- URL: http://127.0.0.1:8000/login
- Directory: `C:\Users\cabas\Documents\Codex\Projects\Training Assessment Tracker V2\platform`
- Database: `platform/.runtime/m2-browser.sqlite` — fresh synthetic M1+M2 schema; separate from the original database.
- Restart from `platform/`: `powershell -ExecutionPolicy Bypass -File scripts/serve-m2-demo.ps1`. No npm build is required. The script never resets or migrates data and suppresses request logging to protect private link paths.
- Global Admin: `admin@example.invalid`. Member: `demo-member@example.invalid`. Both use synthetic-only password `Fifteen plus characters!`. Do not reuse it for real accounts.
- Additional `browser-member@example.invalid` is a synthetic qualification account. Legacy seed identities without activated local passwords cannot sign in by password.
- Local-password/test mode only; SMTP, live AI and live integrations disabled. No worker or scheduler started.

## Backup and rollback

Original local configuration is backed up at `.runtime/m2-main-activation/original.env` (private, ignored). Baseline branch, original database hash and hashes of 29 pre-existing untracked files are in `.runtime/m2-main-activation/baseline.json`. Original database SHA-256: `EE02294A6A20D7AD1BC03D92F5A5628843CBC10CEE22134936F26FEACD28BA33`.

To roll back local configuration, first stop only this preview's recorded listener/launcher after verifying its process command; current process metadata is in `.runtime/m2-main-activation/listener.json` and `launcher.pid`. Restore `original.env` to `platform/.env` and clear the configuration cache. The original database is untouched and available. This returns to the prior local configuration, not a claim that pending historical migrations were resolved. Preserve the new demo database and unrelated working-tree changes; no Git reset or deletion is needed.

## Validation and boundaries

The main-checkout focused M2/M1 suite passed 33 tests / 272 assertions using forced in-memory SQLite. Both PHPUnit configurations now explicitly choose mock authentication so the local-password demo setting cannot break historical fixture seeding or redirect tests to the demo database.

The first artisan-based preview rejected a valid synthetic login despite successful CLI credential checks. Replaced only the task-started process with the direct PHP router and explicit database, session-cookie and configuration isolation. Do not count the first browser run as a pass. Final browser results are recorded below after verification.

No production deployment, remote push, merge, paid usage, real staff records, email delivery or live integration. Saturnino refinement, project-owner milestone acceptance, hosted CI and full M2 remain separate gates.

## Runtime ownership blocker discovered

Port 8000 is currently owned by the pre-existing `scripts/demo-all-ports.mjs` launcher (observed launcher PID 6460, PHP listener PID 30472), which also owns ports 8123/8124/8125/8129. HTTP diagnostics showed its separate realistic-demo database (31 users), while the M2 CLI verified the fresh six-user database and valid local credentials. No final M2 browser pass is claimed on port 8000. Removed the temporary loopback diagnostic file and stopped only the unsuccessful M2 processes; preserved the grouped previews. User choice requested: replace the grouped previews to use 8000, or preserve them and use 8001. Until that choice is received, the main-checkout code/configuration and fresh database are ready, but the requested M2 preview is not active. Original database and all 29 unrelated file hashes rechecked unchanged.

## 2026-10-28 — Runtime blocker resolved; main preview verified

The user explicitly authorised replacing the grouped previews and using port 8000. Stopped the verified old grouped launcher and its five PHP listeners. M2 now runs from the main checkout on port 8000 (observed launcher PID 33504, PHP listener PID 7800); ports 8123, 8124, 8125 and 8129 are closed. Process IDs are observations, not permanent identifiers: verify ownership before stopping a process.

Main-checkout browser verification passed: new account creation and password setup; member login; wrong-recipient denial; invitation acceptance and two-client selection; expired link state; recovery and other-session revocation; own-password change; keyboard login; desktop/mobile rendering and mobile overflow checks. The provided demo-member credentials were checked separately, including client selection and HTTP 403 for platform administration. Safe screenshots are in [main preview evidence](2026-10-28-M2-main-preview/RESULTS.md). The earlier runtime blocker above is historical and resolved.

The active database, credentials, restart and rollback instructions above remain current. The original database and 29 pre-existing unrelated files remain unchanged. Main-checkout tests remain 33 passed / 272 assertions. Local preview is ready and left running; this does not claim full M2 acceptance. Saturnino's UI refinement and separate accessibility review, project-owner acceptance, hosted CI, authentik and live Cerebru remain outstanding.
