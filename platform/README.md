# Training Assessment Tracker V2 — application

B01/B02 local foundation: scoped Blade/Livewire workspaces, synthetic identity/delegation and directory harnesses, private files, audit records, database queue and transactional outbox. Live OIDC/MFA, primary integration and scanning remain pending.

B03 learning setup adds a resumable onboarding wizard, manual assessment confirmation, shared member calendars, versioned curriculum editing and exact-version coordinator approval. Open an enrolment and choose **Onboarding and plan setup**. See the [Milestone 02 guide](docs/MILESTONE_02.md) for the complete workflow and limits.

## Local setup

Requirements: PHP 8.2+ with PDO SQLite (or PDO MySQL for MariaDB), mbstring, OpenSSL, fileinfo, DOM/XML and cURL; Composer. Node is only needed for browser tests.

From this directory:

```powershell
composer install
php scripts/setup.php
php artisan serve --host=127.0.0.1
```

Open http://127.0.0.1:8000 and select a clearly labelled synthetic test identity. Setup preserves an existing .env, application key and database; it does not reset records. The SQLite database and private files are ignored by Git.

In separate terminals:

```powershell
php artisan queue:work --queue=notifications --tries=3 --timeout=120
php artisan schedule:work
```

The first processes local notification receipts. The second recovers committed outbox work if initial queue dispatch was lost. Neither sends email. Static CSS is checked in; Livewire serves its own JavaScript, so the application has no Node runtime/build dependency.

## Verification

GitHub Actions runs the PHP/style/Blade checks on SQLite, the full MariaDB 11.4.13 suite including concurrent queue workers, and Chrome browser/accessibility tests on each pull request and push to main. The workflow uses isolated synthetic databases and retains browser reports for seven days. See [Foundation CI](../.github/workflows/ci.yml).

```powershell
php artisan test --compact
php vendor/bin/phpunit -c phpunit.mariadb.xml
php vendor/bin/pint --test
npm.cmd ci
npm.cmd run test:browser
```

On non-Windows systems use npm instead of npm.cmd. Browser tests require installed Google Chrome, reset only the isolated .runtime/browser.sqlite fixture and use a temporary web server on 127.0.0.1:8123. MariaDB tests require an isolated training_foundation_test database; read the operations guide before running them. Never supply production database credentials.

- [Milestone verification](docs/MILESTONE_01.md)
- [Identity and directory trust contract](docs/IDENTITY_AND_DIRECTORY_CONTRACT.md)
- [Operations, rollback boundaries and user guide](docs/OPERATIONS.md)
- [Requirements-to-code-and-test map](docs/REQUIREMENTS.csv)
- [Project implementation rules](../AGENT.md)
