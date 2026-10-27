# M1 Bernie: approved mapping and isolated backfill rehearsal

Date: 2026-10-26, Asia/Manila. Contributor: Bernie, Developer A. Task: `M1-BERNIE/backfill-rehearsal`. Branch: `codex/m1-approved-client-foundation`; HEAD: `418dd7f5c2237d1c11d73bced4e6b11a26a4eca7`, plus preserved uncommitted foundation and rehearsal changes. No commit, push, merge, deployment or external message. Actual hours unknown.

## Approved scope and mapping

The user explicitly approved the plan and requested implementation: both Operations and Customer Support are internal teams; retain both existing enrolment coordinators as primary accountable coordinators; new permissions begin at the rehearsal timestamp. This resolves the group-classification blocker in the [previous mapping report](2026-10-26-M1-BERNIE-mariadb-mapping/MAPPING-REPORT.md), which is retained as historical evidence.

Consumes the [approved architecture decisions](2026-10-26-approved-architecture-decisions.md), [foundation handoff](2026-10-26-M1-BERNIE-approved-client-foundation.md) and [MariaDB qualification](2026-10-26-M1-BERNIE-mariadb-mapping.md). Requirements: V2-01, V2-02, V2-12, V2-13; PDF sections 3–7 and 23–25. Internal C01/C02 semantics and all existing application consumers remain unchanged.

[Mapping manifest](2026-10-26-M1-BERNIE-backfill-rehearsal/mapping-manifest.json), version 1, creates only:

- Five client memberships, two internal-team classifications and two dated team memberships.
- Five scoped grants: one Client Admin, two team Coordinators and two client Members.
- Two primary allocations; no Global Admin, backups, sensitive capabilities or external identity links.

All new relationship timestamps are `2026-10-26 08:09:03 UTC` / **16:09:03 Asia/Manila**. Legacy dates, identifiers and ownership are retained. New foundation records receive their own IDs. Existing legacy sensitive flags and external identifiers are preserved as source data; they do not create new-model capabilities or identity links.

Manifest content SHA-256: `9227f028333395f954451da8b3793adf67a20a63c307450a14cb49d3f6139d7b`. This is the compact JSON content hash used by the runner, not the pretty-printed file's byte hash. Frozen SQLite snapshot SHA-256: `3f3524ee26e38348e6dd2c29de4e7f1f21969b6832931cc59a37f154e704495e`.

## Implementation and compatibility

- `platform/scripts/support/ClientBackfill.php`: local-only validation and transactional mapping. Requires test environment, an explicitly named disposable target and prepared source/manifest/legacy hashes. Recomputes the approved mapping from the copied legacy relationships; rejects unresolved, non-synthetic, inactive, future/ended, duplicate, self-review and foreign-client relationships or existing foundation conflicts. This bounded tool supports the approved single-client source; it is not a general production importer.
- `platform/scripts/prepare-client-backfill.php`: accepts an untouched SQLite copy inside `.runtime/client-backfill/`; checks eligibility before additive DDL, compares all legacy row hashes, creates a rehearsal-only control table, and exclusively writes the manifest. It does not migrate the application's migration ledger or expose a runtime bootstrap endpoint.
- `platform/scripts/client-backfill-rehearsal.php`: explicit `validate` or `apply`, manifest hash, source file and target required. No database URL or implicit production configuration allowed. Source and target must differ. Validation makes no durable writes. Changed source bytes or a changed manifest are rejected.
- `platform/scripts/rehearse-client-backfill.py`: read-only synthetic source capture, fresh disposable copies, validation/application/retry, explicit wrong-original-target refusal, pre-application snapshot restore, revalidation/reapplication and evidence generation. Never overwrites an existing evidence directory or backup destination.
- New feature/integration tests and `platform/tests/Support/client-backfill-worker.php` exercise the same mapping implementation on SQLite and MariaDB. No application service, migration, route, public API, UI or dependency changes were required. No Postman collection is needed for this database/internal-service slice.

The prepared control row binds manifest identity before application. Mapping acquires the same organisation lock used by directory mutations, then locks the control row. Reads determining the write occur after lock acquisition. One transaction creates all new records, updates only `groups.kind`, adds a single `client.rehearsal.applied` audit event and marks completion. Any error rolls back the data changes. Repeating a completed manifest returns `already_applied` with zero writes, including after later revocation; it never repairs/revives permissions silently.

The new audit event has no authenticated actor and explicitly records offline approval provenance; legacy synthetic Client Admin IDs are attribution on the newly mapped grants/allocations. No existing user is secretly given platform bootstrap authority. The rehearsal deliberately preserves legacy users and sessions byte-for-row: a future application cutover must separately qualify session/permission invalidation and consumer integration.

## Source, isolation and recovery evidence

The source had changed since the preceding turn's inventory. Read-only comparison found audit/cache/session differences but unchanged mapping candidates. It changed again before capture; no cause or actor is inferred. The fresh captured original hash was `027d460a9e5d1dca300181adb8cd6edc9e6cafb5325a450dcf747ba95b972f44`, identical before/after the actual rehearsal. SQLite's backup snapshot has its own hash above; both hashes are recorded distinctly.

[SQLite rehearsal results](2026-10-26-M1-BERNIE-backfill-rehearsal/sqlite-rehearsal-results.json) and [preparation evidence](2026-10-26-M1-BERNIE-backfill-rehearsal/preparation.json) record:

- Full synthetic source captured through SQLite read-only/query-only access; original file never migrated or backfilled.
- All legacy table row counts and hashes equal after application, excluding only the intended new audit event and new group-kind field. No legacy identities, periods, approvals, submissions, evidence metadata or audit rows changed.
- Validation leaves target bytes unchanged; an identical rerun leaves every table's rows unchanged.
- Actual counts: 5 memberships, 2 team memberships, 5 grants, 2 primary allocations.
- Attempt to use the original application database as an apply target refused with exit 2 before writes.
- Pre-application backup restored to a separate disposable file with equal byte hash; validation and application succeeded again, preserving every legacy row. No populated foundation `down()` was used.

Recovery evidence is for the full SQLite snapshot. MariaDB uses isolated representative fixtures, including a manually approved grade decision and programme approval; it is not a full SQLite-to-MariaDB data conversion or a MariaDB backup/restore drill. Private evidence files were not copied or restored; evidence metadata preservation does not certify object-store recovery.

MariaDB 11.4.13 runs only on the previously verified task data directory, loopback port 13318, and database `training_foundation_test`, using its database-limited account. Guard checks run before fixture reset. The installed XAMPP database/service is not used. Credentials, database copies and generated runtime configuration remain ignored under `.runtime/`; evidence contains no assessment bodies or credentials.

## Verification and technical self-review

Initial development checks found and corrected two test/tool issues: Laravel returned schema-qualified table names, so snapshot filtering initially included the rehearsal control table; filtering now explicitly requests unqualified names. The MariaDB subclass initially could not access private fixture helpers; visibility is adjusted only in the new test class. Neither issue modified the original database. Subsequent targeted checks passed.

- Seven mapping scenarios passed on SQLite and MariaDB (44 assertions per engine): approved-history preservation/idempotency; validation-only; wrong target/hash/changed manifest; non-synthetic and wrong-environment relationships; existing foundation conflicts; injected failure with complete rollback/retry; scoped access, new effective dates, primary-only management and revocation.
- Two independent-process MariaDB races passed (18 assertions): identical applications yield exactly one application and one audit event; revocation attempts the held client lock, waits for application, then revokes and remains revoked after retry. [Concurrency JUnit](2026-10-26-M1-BERNIE-backfill-rehearsal/mariadb-concurrency.xml).
- Targeted Pint, PHP syntax checks and Python syntax check passed. The full source snapshot/recovery harness passed.

Final regression and shutdown:

| Check | Actual result | Evidence |
| --- | --- | --- |
| Full MariaDB-configured regression | 152 total; 148 passed, 4 skipped; 843 assertions; 369.979 seconds; no errors/failures | [MariaDB JUnit](2026-10-26-M1-BERNIE-backfill-rehearsal/mariadb-full.xml) |
| Full in-memory SQLite regression | 134 total; 130 passed, 4 skipped; 714 assertions; 43.088 seconds; no errors/failures | [SQLite JUnit](2026-10-26-M1-BERNIE-backfill-rehearsal/sqlite-full.xml) |
| Final isolation | PID 10384 exited; port 13318 closed; original hash still equals capture | [Isolation check](2026-10-26-M1-BERNIE-backfill-rehearsal/final-isolation-check.json) |
| Review anchors and credential scan | 0 changed reviewed-source hashes; 0 task database credentials found in evidence | [Source anchors](2026-10-26-M1-BERNIE-backfill-rehearsal/reviewed-source-hashes.json) |
| Whitespace | `git diff --check` passed | Local command |

The MariaDB-configured suite also includes explicitly isolated SQLite feature tests; the inherited MariaDB mapping suite supplies the engine-specific proof. Four skips in each regression: two ZipArchive cases, real Poppler/Tesseract and real ClamAV. These remain unavailable utility checks, not passes. No browser, UI, external API, authentik, live AI/SMTP or hosted CI checks were performed.

Server shutdown completed at 16:22:13 Asia/Manila. Exact final regression commands added `--log-junit ../docs/implementation/handoffs/2026-10-26-M1-BERNIE-backfill-rehearsal/mariadb-full.xml` or `sqlite-full.xml` respectively to the commands below. Concurrency used the MariaDB configuration with `--filter 'ClientBackfillMariaDbTest::test_concurrent' --testdox --log-junit ../docs/implementation/handoffs/2026-10-26-M1-BERNIE-backfill-rehearsal/mariadb-concurrency.xml`.

Formatting command: `php vendor/bin/pint --test scripts/support/ClientBackfill.php scripts/client-backfill-rehearsal.php scripts/prepare-client-backfill.php tests/Feature/ClientBackfillTest.php tests/Integration/ClientBackfillMariaDbTest.php tests/Support/client-backfill-worker.php`. PHP `-l` passed each of those six files; Python `ast.parse` passed the orchestrator. The source-snapshot harness used bundled Python with `-B`, source `database/database.sqlite`, evidence `../docs/implementation/handoffs/2026-10-26-M1-BERNIE-backfill-rehearsal` and PHP `C:\xampp\php\php.exe`.

Technical self-review in Bernie's session: checked original-path refusal, snapshot/hash binding, no implicit target, pre-DDL eligibility, no new Global Admin/sensitive grants, historical-record preservation, atomic control/audit commit, client lock ordering, no stale-grant restoration, test process cleanup and private runtime isolation. No independent technical review is claimed. Previous foundation source hashes remained unchanged. Reviewed source anchors accompany final evidence.

## Reproduction

From `platform/` with Python 3.11+ and the project's PHP dependencies:

```text
python -B scripts/rehearse-client-backfill.py --source database/database.sqlite --evidence <new-evidence-directory> --php <php-executable>
php vendor/bin/phpunit --display-skipped
php vendor/bin/phpunit -c .runtime/m1-qualification-20261026/phpunit.xml --display-skipped
```

The Python harness sets explicit test-only configuration and creates fresh runtime paths. For manual PHP invocation, explicitly set `APP_ENV=testing`, `TRAINING_ENVIRONMENT=test`, `DB_CONNECTION=sqlite`, `DB_DATABASE=<disposable-target>`, an unused test `APP_CONFIG_CACHE`, and clear `DB_URL`. Use `--manifest`, `--manifest-sha256`, `--source=<frozen-snapshot>`, `--target=<same-disposable-target>` and `--mode=validate|apply`. The generated manifest applies only to its prepared copy and source fingerprint; never retarget it to the application database. MariaDB commands require the verified task instance running and `M1_EXPECTED_DATADIR`; no credentials are embedded in these instructions.

## Remaining gates and next action

This bounded implementation is complete locally and ready for review, with no unresolved mapping decisions for these five synthetic users. It supplies an approved mapping manifest and isolated backfill/recovery evidence. It does not authorize production mapping or establish full M1 acceptance. The current runner intentionally refuses an already populated unrecorded foundation, ambiguous history, broader source scope or altered manifests rather than merging uncertain access.

Next Bernie work: plan and implement coordinated consumer integration, including HTTP/Livewire, background execution, downloads, reports/aggregates, approval paths and AI context, plus qualified session invalidation. Sensitive capabilities, limited departing-member history, backup activation and authentik configuration remain separate policy/integration gates. Any newly needed business choice requires clarification.

Saturnino has no changed UI to review in this slice. Consume the handoff as backend evidence; bind UI only to a reviewed consumer contract and provide separate UX/accessibility review then. Shared contracts, shared status, counterpart records and prior handoffs are unchanged; no distributed lock or acknowledgement inferred. Ticket-linked PR/hosted CI, project-owner milestone acceptance and production release remain pending. No paid/live operation occurred.
