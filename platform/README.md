# Training Assessment Tracker V2 — application

B01/B02 local foundation: scoped Blade/Livewire workspaces, synthetic identity/delegation and directory harnesses, private files, audit records, database queue and transactional outbox. Live OIDC/MFA, primary integration and scanning remain pending.

B03 learning setup adds a resumable onboarding wizard, manual assessment confirmation, shared member calendars, versioned curriculum editing and exact-version coordinator approval. Open an enrolment and choose **Onboarding and plan setup**. See the [Milestone 02 guide](docs/MILESTONE_02.md) for the complete workflow and limits.

B04 adds queued block generation, draft revision notes, a deterministic synthetic mock, Anthropic/Gemini HTTP adapters, administrator task routing, cost reservations and failure reconciliation. See [Milestone 03](docs/MILESTONE_03.md). Real providers are disabled; HTTP contract tests do not establish live calibration.

B05–B09 local implementation adds lesson delivery, recoverable drafts, immutable submissions, deterministic quizzes, provisional grading and human decisions, appeals, KPI lineage, N+2 adaptation, pause/resume previews, completion, administration, promotion/feed contract boundaries, notifications and operational rehearsals. See the [delivery guide](docs/LOCAL_WORKFLOWS.md), [verification ledger](docs/REMAINING_MILESTONES.md) and [deployment preparation](docs/DEPLOYMENT_PREPARATION.md). Live services are not configured; this is a local release candidate, not an authorised production launch.

## Local setup

Requirements: PHP 8.2+ with PDO MySQL, mbstring, OpenSSL, fileinfo, DOM/XML and cURL; Composer; MariaDB (XAMPP or standalone). Node is only needed for browser tests. SQLite remains available for existing isolated tests. The installer accepts MariaDB 10.4+ and hosted MySQL 8.0+; acceptance of a version is not evidence that every version has been qualified.

From this directory:

```powershell
composer install
php artisan serve --host=127.0.0.1
```

Before starting, create a **new empty database** in phpMyAdmin (for example `training_tracker_local`, using `utf8mb4_unicode_ci`). The database account needs SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES and DROP privileges on that database for migrations. The wizard does not create databases, server users or grants. XAMPP commonly uses host `127.0.0.1`, port `3306`; enter your actual username and password rather than assuming a blank root password.

Open http://127.0.0.1:8000. A fresh clone opens `/setup`: check requirements, test database credentials, enter your personal Global Admin details and passwords, then review and install. Do not run `key:generate`, migrations or seeders before the wizard. It generates the key, configures `.env`, migrates the empty database and creates three fictional clients, 30 demo accounts and 21 training scenarios. Your personal administrator is separate. Use at least 15 characters and at most 72 bytes for passwords. Neither password is committed or printed.

Demo emails are `demo.CLIENT.PERSON@example.invalid`: CLIENT is 1–3; PERSON 0 is Client Admin, 1–2 are Coordinators and 3–9 are Members. All use the demo password you chose. Some fixtures deliberately demonstrate departed members and restricted access. Mock identity selection is disabled. Local access is loopback-only, and live AI/email remain disabled. On subsequent starts use `php artisan serve` and sign in normally.

### Existing clones using SQLite

Stop your server and workers, preserve uncommitted work, update to the team revision containing the installer, and run `composer install`. Create a separate empty MariaDB database in phpMyAdmin. From `platform` run:

```powershell
php artisan training:prepare-local-upgrade
php artisan serve --host=127.0.0.1
```

Enter the printed private one-hour token at `/setup`. The command backs up `.env` under private `storage/app/installation` and selects `APP_ENV=local` so `php artisan serve` starts with the correct profile; it does not modify SQLite or its private files. The new installation keeps the application key but creates new accounts/data and a separate private-storage directory. Old records are preserved, not transferred. Existing wizard installations cannot reopen setup. If a legacy configuration is cached, inspect it and run `php artisan config:clear` before the upgrade; do not change the current database just to make this command pass.

### Fresh hosted installation

Use a fresh checkout with its document root at `platform/public`, an empty MariaDB or MySQL database, and HTTPS. Run `composer install --no-dev`, copy `.env.example` to `.env` if absent, set `APP_URL` to the exact HTTPS origin and `TRAINING_INSTALL_MODE=hosted`, and leave `APP_KEY` blank. Keep configuration uncached until installation finishes. Configure trusted proxies for the actual host if TLS terminates upstream; do not trust arbitrary forwarded headers.

```text
php artisan training:setup-token
```

Visit `/setup` over HTTPS and paste the token into the form, never into the URL. It expires in one hour and is consumed when exchanged for a private setup session. Generate another token to resume after session expiry; this invalidates the previous operator session. Hosted setup creates only your personal Global Admin, enables the existing guarded password-login mode, and does not seed demo records. Live integrations and production qualification remain separate. Do not run a public first install over HTTP.

### Installer recovery and privacy

- Installation state is private under `storage/app/installation`; database ownership and stages are recorded in `training_installations`. Back up these records together with `.env`, the application key, database and private files. Never expose `storage` as a web root.
- Setup will not reset a non-empty database, overwrite another installation or reopen merely because the database is unavailable. A missing key/configuration on an established installation requires operator recovery, not reinstallation.
- A failed connection keeps non-secret fields and asks you to re-enter the database password. Fix permissions/availability and retry an interrupted stage. Migrations use non-transactional DDL on MySQL/MariaDB: interruption inside a migration can require operator inspection and repair before retry; setup never drops partially created application tables automatically.
- Preserve the same database when resuming. For an abandoned local upgrade, stop processes, restore `environment-before-upgrade.env` to `.env`, and archive the new installation state outside the active storage directory. Preserve both databases. Completed installations are recovered from backups, not through the wizard.
- To replace an expired local-upgrade token, run `php artisan training:setup-token`. Resume with the same administrator name, email and passwords used when installation started; setup will not change already-created credentials.
- Keep inherited `APP_*`, `DB_*`, `TRAINING_*` and session environment overrides out of the server process unless intentionally managed; they can override `.env`. Stop old servers/workers and start a new process after installation. Check occupied ports rather than terminating unrelated services.
- The wizard stores database passwords in `.env` as `TRAINING_DB_PASSWORD_B64`, which takes precedence over `DB_PASSWORD`. This preserves special characters and Laravel-reserved literal values. Base64 is encoding, **not encryption**: protect `.env` as a credential file. For manual password changes update that encoded value, or remove it before using `DB_PASSWORD`.
- Synthetic mock sign-in remains an explicit legacy testing workflow: configure a separate local/test SQLite `.env` with `TRAINING_AUTH_MODE=mock` and `MOCK_IDENTITY_ENABLED=true`, then run `php scripts/setup.php --demo`. Never point demo setup at an existing account database.

In separate terminals:

```powershell
php artisan queue:work --queue=notifications --tries=3 --timeout=120
php artisan queue:work --queue=ai_generation --tries=1 --timeout=120
php artisan queue:work --queue=ai_grading,extraction,integration --tries=1 --timeout=120
php artisan schedule:work
```

Run these as separate processes. The notification worker processes local receipts; the AI worker processes one bounded block per job. The scheduler recovers dispatch gaps, flags interrupted provider calls and removes old temporary AI traces. Local defaults make no AI network calls and send no email. Static CSS is checked in; Livewire serves its own JavaScript, so the application has no Node runtime/build dependency.

## Verification

GitHub Actions runs the PHP/style/Blade checks on SQLite, the full MariaDB 11.4.13 suite including concurrent queue workers, and Chrome browser/accessibility tests on each pull request and push to main. The workflow uses isolated synthetic databases and retains browser reports for seven days. See [Foundation CI](../.github/workflows/ci.yml).

```powershell
php artisan test --compact
php vendor/bin/phpunit -c phpunit.mariadb.xml
php vendor/bin/pint --test
npm.cmd ci
npm.cmd run test:browser
php scripts/restore-drill.php
node scripts/load-rehearsal.mjs
```

On non-Windows systems use npm instead of npm.cmd. Browser tests require installed Google Chrome, reset only the isolated .runtime/browser.sqlite fixture and use a temporary web server on 127.0.0.1:8123. MariaDB tests require an isolated training_foundation_test database; read the operations guide before running them. Never supply production database credentials.

- [Milestone verification](docs/MILESTONE_01.md)
- [Identity and directory trust contract](docs/IDENTITY_AND_DIRECTORY_CONTRACT.md)
- [Operations, rollback boundaries and user guide](docs/OPERATIONS.md)
- [Requirements-to-code-and-test map](docs/REQUIREMENTS.csv)
- [Project implementation rules](../AGENT.md)

Document extraction, OCR and coordinator review: [setup and workflow](docs/DOCUMENT_PROCESSING.md). Realistic manual test inputs: [mock data pack](docs/REAL_WORLD_MOCK_TEST_DATA.md).

## Cerebru UI and branding

The shared UI now includes a Cerebru-style sidebar, five persistent themes, responsive navigation and configurable deployment/organisation branding. See [configuration, previews and integration limits](docs/CEREBRU_UI.md).
