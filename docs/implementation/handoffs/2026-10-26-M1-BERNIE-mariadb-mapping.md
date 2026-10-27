# M1 Bernie: MariaDB qualification and mapping handoff

Date: 2026-10-26, Asia/Manila; final server shutdown verified at approximately 15:46. Contributor: Bernie, Developer A. Task: M1-BERNIE, bounded MariaDB integrity/concurrency qualification and read-only existing-data mapping. Status: local qualification complete; mapping decisions and full M1 remain pending.

Branch `codex/m1-approved-client-foundation`, HEAD `418dd7f5c2237d1c11d73bced4e6b11a26a4eca7`, plus uncommitted working-tree changes. No commit, push, PR, merge or deployment. Existing changes and earlier handoffs preserved. Actual hours unknown.

Consumes [approved architecture decisions](2026-10-26-approved-architecture-decisions.md) and [foundation handoff](2026-10-26-M1-BERNIE-approved-client-foundation.md). Requirements: V2-01, V2-02, V2-12, V2-13; PDF sections 3–7 and 23–25. This evidence supersedes the earlier unavailable-MariaDB gate for the tested local foundation only. Internal C01/C02 semantics are unchanged; no HTTP contract is published/frozen and legacy application consumers remain on legacy authorization.

## Changes and defect found

- Fixed the foundation migration's empty rollback on MariaDB. InnoDB had replaced the implicit `groups.organisation_id` FK index with `groups_tenant_identity`; dropping the latter failed with SQLSTATE HY000/1553. The rollback now checks for another index starting with `organisation_id` and restores a legacy-compatible index if necessary before removing the composite index. Foreign key enforcement is never disabled by this fix. Populated-foundation rollback protection remains in place.
- Added `platform/tests/Integration/ClientMigrationMariaDbTest.php`: fresh synthetic legacy programme approval and manually approved grade decision; row counts and hashes of every legacy table compared through upgrade, empty-foundation rollback and re-upgrade. Foundation tables remain empty until explicitly mapped. No approved history is discarded or rewritten.
- Added `platform/tests/Integration/ClientConcurrencyTest.php` and the support worker: independent PHP processes competing for primary allocation and identical grants; revocation while an organisation lock is held; stale actor read after committed revocation.
- Added `platform/tests/Support/client-qualification-guard.php`: testing environment, exact database, server version and task data-directory checks before destructive fixture reset. CI uses its dedicated service; local runs require `M1_EXPECTED_DATADIR`.
- Added read-only `platform/scripts/client-mapping-dry-run.py`, its four Python tests, [mapping report](2026-10-26-M1-BERNIE-mariadb-mapping/MAPPING-REPORT.md), machine-readable inventory and qualification evidence. Updated Bernie's own record only; Saturnino's state and shared contracts/status are unchanged.

Reviewed code anchors are in [reviewed-source-hashes.json](2026-10-26-M1-BERNIE-mariadb-mapping/reviewed-source-hashes.json), including both existing foundation services and all qualification code. The migration fix is the only foundation runtime change in this continuation; no service or consumer policy was changed.

## Isolation and server lifecycle

The installed XAMPP server version was 10.4.32; it was not used. Downloaded official MariaDB 11.4.13 Windows package to ignored `platform/.runtime/m1-qualification-20261026/`, matching the repository's pinned CI version. Verified archive SHA-256 against the publisher's checksum:
`d62986d433eeebfde218560b276103831604a61e929e87f1a17f5aebd80257e2`.

Official package: `https://archive.mariadb.org/mariadb-11.4.13/winx64-packages/mariadb-11.4.13-winx64.zip`; checksum file in the same directory. An initial download timed out; a subsequent verified range download succeeded. No application dependency or Windows service was installed.

Started a hidden task-owned process, PID 34188, bound only to `127.0.0.1:13318`, with data directory `platform/.runtime/m1-qualification-20261026/data/`. Provisioned only `training_foundation_test` and a random-password account limited to that database. Root was used only for local provisioning/shutdown. Generated an ignored test configuration from `phpunit.mariadb.xml`, forcing the port, database, account, test key and expected data directory; `.env` and the checked-in PHPUnit configurations were unchanged. Credentials are not in handoff evidence.

The test server was gracefully shut down after qualification. [Final isolation check](2026-10-26-M1-BERNIE-mariadb-mapping/final-isolation-check.json) verifies the process exited, port 13318 stopped listening and the existing application SQLite file hash is unchanged. Runtime data/configuration remain ignored local artifacts. Re-running requires explicitly restarting the verified isolated instance; never redirect these reset tests to the existing application database.

## Actual checks

Commands ran from `platform/`, with PHP 8.2.12 and MariaDB 11.4.13. PHP execution needed approved host execution because the restricted runner could not read vendor autoload files.

| Check | Actual result | Evidence |
| --- | --- | --- |
| Initial targeted MariaDB qualification | 21 tests, 107 assertions; one rollback error HY000/1553 | [Initial JUnit](2026-10-26-M1-BERNIE-mariadb-mapping/mariadb-initial.xml) |
| Targeted qualification after index fix | 21 passed, 117 assertions, 46.315 seconds | [Targeted JUnit](2026-10-26-M1-BERNIE-mariadb-mapping/mariadb-targeted.xml) |
| Full MariaDB-configured suite | 136 total, 132 passed, 4 skipped, 737 assertions, 124.735 seconds; no errors/failures | [MariaDB JUnit](2026-10-26-M1-BERNIE-mariadb-mapping/mariadb-full.xml) |
| Full in-memory SQLite suite | 127 total, 123 passed, 4 skipped, 670 assertions, 18.599 seconds; no errors/failures | [SQLite JUnit](2026-10-26-M1-BERNIE-mariadb-mapping/sqlite-full.xml) |
| Python mapping tests | 4 passed, 0.029 seconds on final run | `python -B tests/Support/test_client_mapping.py` |
| Targeted Pint | Passed all five changed/new PHP files listed below | Command below |
| Final source preservation / whitespace | SQLite byte hash unchanged; `git diff --check` passed | Final isolation check / local command |

The MariaDB-configured run includes the existing migration feature test that deliberately uses its own SQLite in-memory connection; the dedicated MariaDB migration test supplies the engine-specific evidence. Four utility skips in both full runs: two ZipArchive tests, real Poppler/Tesseract, real ClamAV. They remain unverified, not clean results. No browser, UI, authentik, external AI, SMTP, live integration or hosted CI was run.

```text
php vendor/bin/phpunit -c .runtime/m1-qualification-20261026/phpunit.xml --filter 'Client(Foundation|Concurrency|Migration)' --testdox --log-junit ../docs/implementation/handoffs/2026-10-26-M1-BERNIE-mariadb-mapping/mariadb-targeted.xml
php vendor/bin/phpunit -c .runtime/m1-qualification-20261026/phpunit.xml --display-skipped --log-junit ../docs/implementation/handoffs/2026-10-26-M1-BERNIE-mariadb-mapping/mariadb-full.xml
php vendor/bin/phpunit --display-skipped --log-junit ../docs/implementation/handoffs/2026-10-26-M1-BERNIE-mariadb-mapping/sqlite-full.xml
php vendor/bin/pint --test database/migrations/2026_10_26_120000_create_client_foundation.php tests/Integration/ClientConcurrencyTest.php tests/Integration/ClientMigrationMariaDbTest.php tests/Support/client-concurrency-worker.php tests/Support/client-qualification-guard.php
python -B tests/Support/test_client_mapping.py
```

Python used the bundled Python runtime. The initial targeted command was identical to the targeted command above except its evidence filename was `mariadb-initial.xml`. Exact inventory reproduction and safety rules are in the mapping report. Do not overwrite retained evidence when repeating checks.

## Permission and integrity evidence

The four new concurrency scenarios passed: two primary contenders produce exactly one success, one conflict, one active allocation and one audit event; identical grant contenders produce exactly one active grant and one audit event; a delayed admin mutation denies with 403 after revocation and creates no group; a previously loaded actor fails a subsequent directory access check after revocation.

Foundation tests also passed against MariaDB for cross-client/actor FK rejection, multiple clients/teams, teamless explicit allocation, scoped administration, no legacy-admin bypass, self-approval denial, stale identity and grants, departure/rejoin, explicit primary replacement and populated rollback refusal. These are local fixture proofs, not exhaustive race detection or a full application security cutover.

## Technical self-review and remaining gates

Technical self-review completed in Bernie's session against the source hashes above. Reviewed pre-mutation database guard, restricted task account, unchanged original DB hash, row-hash preservation, FK-index restoration without disabling constraints, current authority after lock acquisition, duplicate/audit behavior, exclusive report creation and absence of credentials/personal bodies in evidence. This is implementation self-review, not independent approval.

The requested local qualification and dry-run inventory are delivered. Remaining blockers and next work:

1. **Mapping decision — user/project owner:** Operations and Customer Support are not yet classified. The user requested an explanation after selecting “one of each”; no named mapping was approved. Review explicit grant dates, primary allocations and sensitive capabilities as part of the mapping manifest. No inferred global-admin grant or external identity linking.
2. **Next Bernie slice:** after mapping decisions, build and test an explicit idempotent backfill on an isolated synthetic copy. The current rehearsal proves additive schema preservation, not populated grant backfill, interrupted-DDL recovery, backup restoration or production rollout readiness. Populated foundation down still intentionally refuses data loss; restore/reconcile procedures need separate qualification.
3. **Application integration — Bernie:** HTTP, Livewire, jobs, downloads, aggregates, exports, AI and approval consumers still use legacy policy. Integrate and test the complete boundary before advertising new-model protection. Authentik configuration, sensitive capabilities, backup activation and optional historical-access policy remain separate gates.
4. **Saturnino:** no UI changes to review in this slice. Use this evidence as producer context; bind UI only after the applicable consumer contract is ready, then supply separate UX/accessibility review.
5. **Delivery acceptance:** ticket-linked PR/hosted CI and project-owner M1 acceptance remain pending. No remote actions were authorised or taken. No paid-service operation occurred. Actual hours and revised estimate are unknown.

No shared consumer contract files were modified or released. Earlier C01/C02 artifacts, foundation changes, public images and Word lock file were preserved. No cross-machine coordination acknowledgement is claimed.
