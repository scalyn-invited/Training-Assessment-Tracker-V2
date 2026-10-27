# M1-BERNIE — application permission integration

Date: 2026-10-26. Owner: Bernie. Status: **ready_for_review for this local implementation slice**. Full M1 acceptance remains pending.

## Revision and boundaries

Base revision: `418dd7f5c2237d1c11d73bced4e6b11a26a4eca7`, branch `codex/m1-approved-client-foundation`. Delivery is an uncommitted working-tree change. The accompanying `source-manifest.json` identifies the exact reviewed file contents; HEAD alone does not identify this delivery. Previous foundation/backfill artifacts, unrelated images, Word lock file and counterpart records were preserved. This continues the latest foundation/backfill handoff under the user's explicit application-integration plan.

No existing application database migration, push, merge, deployment, paid service, live identity connection or external message occurred. The application database SHA-256 remained `027d460a9e5d1dca300181adb8cd6edc9e6cafb5325a450dcf747ba95b972f44`. The new application consumers require the new schema and explicit authority: the untouched legacy database is not a runnable cutover target for this code. Cutover remains a separate approved operation.

## Implemented contract

- Server-validated client context, explicit selection for multiple clients, automatic selection for one client, minimal labelled switcher and authenticated selection actions. Persisted ownership and current client membership establish authority; submitted identifiers and legacy user role/organisation/sensitive flags do not.
- Central SQL-scoped summary, detailed/self, primary management, sensitive-file and appeal-case queries. Dashboard, review queues, notifications and API lists are filtered before pagination/counting. Summary-only viewers receive a restricted summary screen. Client/Global Admin directory management does not automatically expose training details.
- Dated sensitive grants reference client, allocation, grantor and reason. A different active Client Admin must grant them. Revoke/relationship expiry preserves history, increments recipient permission versions and deletes all recipient sessions. Allocation or role re-entry cannot revive an ended grant. Backup access does not confer primary approval authority; self-approval remains denied.
- Explicit appeal authority is bound to its qualifying team role and the exact submission. Original decision-maker/member exclusions remain. Resolution, reassignment and lost eligibility invalidate sessions and close authority; role re-grant alone does not restore it. Case access does not expose unrelated assessments or prior private grading attempts.
- Existing controllers, Livewire receipts, administration, onboarding, learning, grading, progress/KPI tasks, file downloads/extraction, notifications, AI generation/task execution, approved feeds and queued promotions use the scoped boundary. Protected learning and permission mutations serialize on the client lock and recheck current authority. Background cancellation audits use persisted client ownership even after access is lost. Promotions now persist client/environment and fail closed when unbound.
- Members retain their own history after team departure while active in the client. Teamless enrolments use explicit allocations. The compatibility migration makes the existing enrolment team reference nullable without changing historical rows. Legacy fields and relationships remain historical data.
- The existing three sandbox API routes require `X-Client-Id`. The [Postman collection and placeholder environment](../../../platform/docs/postman/README.md) cover permitted/missing/foreign/stale/revoked cases. Authentication is still the existing sandbox mechanism, not authentik.
- Synthetic seeding now supplies explicit client memberships, scoped roles, allocations and separately granted sensitive access. Legacy-source rehearsal fixtures deliberately reconstruct unmapped synthetic data; there is no runtime legacy-permission fallback.

## Actual verification

Raw JUnit reports and machine-readable results are in [the evidence directory](2026-10-26-M1-BERNIE-permission-integration/verification-results.json).

| Check | Actual result |
| --- | --- |
| Final complete SQLite suite | 145 tests, 801 assertions: **141 passed, 4 skipped**, no errors/failures |
| Broad MariaDB 11.4.13 run | 163 tests, 922 assertions: 155 passed, 4 errors, 4 skipped; all four errors were old teardown attempts to roll back populated permission history |
| MariaDB cleanup/concurrency recheck | 20 tests, 143 assertions, all passed after guarded disposable-test teardown correction |
| Final MariaDB permission/appeal/migration follow-up | 20 tests, 139 assertions, all passed |
| SQLite final permission/appeal/migration follow-up | 20 tests, 155 assertions, all passed; superseded by complete final SQLite run |
| Formatting and whitespace | Final `pint --test` and `git diff --check` passed |
| Postman files | JSON syntax/shape, eight requests, declared placeholders and empty environment values passed; desktop import/execution not performed |
| Browser | Explicit selection and switching, active-client display, sensitive grant/revoke actions passed on disposable synthetic data; desktop 1440×1000 and mobile 390×844 inspected; mobile document width 390; console zero errors/warnings |

The final MariaDB evidence is a broad run plus successful targeted correction/follow-up runs, **not a claim that a new complete MariaDB run was repeated after every final edit**. Earlier failed SQLite runs are retained separately: initial 134-test run had six errors/ten failures; the next 76-test run had fourteen errors. These exposed legacy test assumptions, missing explicit fixture grants, cancellation-audit provenance and an accidental update argument; all were corrected before the final complete SQLite run.

The concurrency checks include duplicate sensitive grants producing one grant/audit event and one version increment, a waiting grant rejected after its allocation ends, and a waiting real programme approval rejected after sensitive access is revoked. Existing primary-allocation, duplicate-role, stale-actor, AI-budget, capacity and duplicate-worker checks were also exercised. Additive migration checks compare every historical row/count/hash on MariaDB and legacy rows on SQLite, including approved learning history. Populated permission rollback remains refused; the old test teardown was changed rather than weakening that safeguard.

Four utility checks remain skipped: two require PHP ZipArchive, one requires configured Poppler/Tesseract, and one requires real ClamAV. No successful real scanner/OCR result is claimed.

## Commands and isolation

Commands ran from `platform/` unless stated otherwise:

```text
php vendor/bin/phpunit --log-junit .runtime/permission-first.xml
php vendor/bin/phpunit --filter "FoundationTest|DeliveryTest|AiGenerationTest|IntegrationBoundaryTest|NotificationAndScanTest" --log-junit .runtime/permission-second.xml
php vendor/bin/phpunit --log-junit .runtime/permission-third.xml
php vendor/bin/phpunit -c .runtime/m1-qualification-20261026/phpunit.xml --log-junit .runtime/permission-mariadb-first.xml
php vendor/bin/phpunit -c .runtime/m1-qualification-20261026/phpunit.xml --filter "ClientPermissionIntegrationTest|ClientConcurrencyTest|ClientMigrationMariaDbTest|AiConcurrencyTest|CapacityConcurrencyTest|WorkerConcurrencyTest" --log-junit .runtime/permission-mariadb-recheck.xml
php vendor/bin/phpunit -c .runtime/m1-qualification-20261026/phpunit.xml --filter "ClientPermissionIntegrationTest|ClientMigrationMariaDbTest|DeliveryTest" --log-junit .runtime/permission-mariadb-followup.xml
php vendor/bin/phpunit --filter "ClientPermissionIntegrationTest|ClientFoundationMigrationTest|DeliveryTest" --log-junit .runtime/permission-sqlite-followup.xml
php vendor/bin/phpunit --display-skipped --log-junit .runtime/permission-sqlite-complete.xml
php vendor/bin/pint
php vendor/bin/pint --test
node scripts/browser-prepare.mjs
npx --yes --package @playwright/cli playwright-cli -s=m1-permissions open http://127.0.0.1:8123/login --browser=chrome
php .runtime/m1-qualification-20261026/shutdown.php
git diff --check
```

SQLite tests force `:memory:`; browser preparation guards `.runtime/browser.sqlite`. MariaDB used the existing task-owned 11.4.13 binaries/data, loopback port 13318 and database `training_foundation_test`, with the existing identity/datadir guard and private credentials. Never copy credentials into a report. MariaDB PID 35056 and browser PHP PID 28424 were shut down; the dedicated Playwright session was closed. Ports 13318 and 8123 were independently confirmed closed. No destructive recovery was run against the application database or a populated foundation.

## Bernie technical self-review and next gates

Reviewed the manifest contents for client/record binding, stale objects, role/identity freshness, member/team/client departure, self-grants, backup versus primary authority, exact appeal access, queued result delivery, unchanged immutable history and explicit fixture authority. The regression runs demonstrate local behavior; this is technical self-review, not independent approval. No actual hours were supplied.

**Review request for Saturnino:** please separately review the client chooser/switcher, summary-only state, teamless labels, sensitive grant/revoke forms, keyboard/focus behavior, narrow viewport layout and explanatory language. The screenshots and browser observations are evidence, not a substitute for your UX/accessibility review. No counterpart acceptance or full accessibility certification is inferred, and no external message was sent.

Pending: Saturnino's separate UX/accessibility review, hosted CI/PR review, full M1/project-owner acceptance, separately approved application cutover, live authentik/Cerebru integration, and the four unavailable utility checks. Optional departing-member history grants, broader sensitive-access policy work and any new business defaults remain outside this slice.
