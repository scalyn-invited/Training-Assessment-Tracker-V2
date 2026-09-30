# Milestone 01 — local foundation

Verified 30 September 2026. The locally achievable foundation is implemented. B01 live integration proof and the complete B02 security acceptance gate remain open where real services are required.

## Delivered

- Laravel 12.69.3 / Livewire 3.8.10 application, locked Composer and browser-test dependencies, reproducible non-destructive setup.
- Database schema for organisations, UUID users/identities, groups, effective memberships/coordinator assignments, enrolments, private evidence, audit, inbound event ledger, outbox and notification receipts.
- Administrator People view, scoped coordinator/member workspaces, enrolment details, private fixture downloads, quarantine uploads and polling job receipts.
- Shared access rules enforce organisation/environment, active identity/account, effective group membership, named coordinator responsibility, sensitive-file permission and permission freshness.
- Explicitly labelled synthetic sign-in and signed mock delegation; invalid token, wrong audience/client, machine impersonation, stale permission and revocation paths fail closed. Production mock use is disabled.
- Local directory contract adapter handles synthetic person updates/deactivation and mapped membership changes with ordering, deduplication, payload conflicts, ownership checks and session/token invalidation.
- Transactional business event + audit, after-commit dispatch, recovery command/scheduler, bounded worker retries, delivery-time authority/version checks and a durable non-delivering notification sink.
- Requirements map preserves all 40 IDs and distinguishes partial, mocked and pending scope.

## Executed verification

| Check | Result |
| --- | --- |
| PHP feature suite on SQLite | 29 tests passed, 131 assertions |
| PHP suite on MariaDB 11.4.13 | 30 tests passed, 141 assertions |
| Two concurrent real MariaDB queue workers | Duplicate event delivered once; one receipt and completion audit; no pending/failed jobs |
| MariaDB migration rollback | Passed through DatabaseMigrations teardown; foreign key removed before dependent index |
| Playwright on installed Google Chrome | 3 tests passed; process exit 0; 43.1 seconds |
| Automated accessibility checks | No reported WCAG A/AA violations in tested login, coordinator and mobile learner surfaces |
| Browser behaviour | Peer plan denied; private download succeeds; real queued check becomes Delivered through Livewire polling; role navigation and counts scoped |
| Responsive checks | 390px mobile viewport has no horizontal overflow; keyboard skip link reachable |
| Visual review | Desktop coordinator dashboard and mobile learner page screenshots inspected |
| Laravel Pint | Passed |
| Composer validation | Passed with current lockfile |
| Blade compilation | Passed |
| Local setup rerun | Passed without resetting data or rotating the application key |

Commands: `php artisan test --compact`, `php vendor/bin/phpunit -c phpunit.mariadb.xml --testdox`, `php vendor/bin/pint --test`, `composer validate --strict --no-check-publish`, `php artisan view:cache`, and `npm.cmd run test:browser`.

The browser suite uses an isolated synthetic SQLite database and APP_ENV=local, including real CSRF middleware. On this Windows workstation its successful final run needed process-management permissions outside the sandbox so Playwright could stop its own server. Tests do not send messages, access live identity/directory services or use real employee data.

MariaDB was downloaded into the ignored root `.runtime/` directory, verified against the vendor SHA-256 checksum, and run on loopback port 3308 with a dedicated test database. The XAMPP installation was not modified. SQLite remains the convenient local app default.

Snapshots: [desktop coordinator](evidence/coordinator-dashboard.png), [mobile learner](evidence/mobile-plan.png). Automated accessibility scans and these screenshots are not a full manual screen-reader audit or production performance benchmark.

## Known boundaries

- No real OIDC, MFA, recovery, token exchange, JWKS validation or live directory connection. The mock token format is not production OAuth/JWT.
- The sandbox external query endpoints deliberately return no synthetic facts. Complete approved-response schemas, publication, cache invalidation, remote reconciliation and member promotion remain B07 work.
- No real SMTP delivery; receipts stay in the database sink.
- New uploads remain quarantined. Malware scanning, DOCX support, extraction/OCR and real assessment handling are pending. The trusted text fixture demonstrates private download access only.
- No onboarding wizard, usable curriculum, content approvals, submissions/grading, KPI calculation, adaptation, or manual user/assignment management UI yet.
- No production host qualification, restore drill, load test, retention/erasure implementation or live AI calibration. No deployment has occurred.

## Next increment

B03: resumable onboarding, confirmed assessment inputs, shared capacity/calendar rules, competencies, and a versioned learning-content editor. Preserve the access, identity, history and outbox boundaries already established. Real integration decisions can proceed separately under the [trust contract](IDENTITY_AND_DIRECTORY_CONTRACT.md).
