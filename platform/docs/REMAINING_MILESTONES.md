# Remaining milestones — implementation ledger

Started 3 October 2026 from merged Milestone 3. Work in progress; this file is not a completion claim.

Order: B05 learner delivery/review; B06 versioned KPIs and N+2 adaptation; B07 scoped source-platform integration; B08 deployment/operations/restore; B09 isolated pilot harness and human calibration gates.

The user confirmed: **None yet—complete local workflows and deployment preparation.** No live connection, production deployment, paid AI call, real email or staff-data access has been performed.

## Implemented local scope

| Increment | Implementation | Verification |
| --- | --- | --- |
| B05 | Activation, lesson preview/early start, autosaved work, immutable/idempotent receipts, deterministic private-key quizzes, bounded provisional grading, human decisions/overrides, withdrawal/resubmission, alternate-review appeals, in-app notices/preferences, SMTP sink and bounded rejection handling | DeliveryTest, NotificationAndScanTest; browser coordinator/member journey |
| B06 | Effective-dated KPI definitions and evidence snapshots; missing-sample handling; suggestions; source-pinned N+2 proposals; exact freeze/started checks; human publication; pause/resume preview; shared-capacity reservation revisions; completion gate | ProgressionTest, PilotWorkflowTest, existing calendar/capacity concurrency tests |
| B07 | Local people/cohorts/assignments/enrolments; promotion ledger with original-key reconciliation and separate history-export decision; approved facts feed; permission filtering; atomic directory-page cursor/tombstone harness | AdministrationTest, IntegrationBoundaryTest, existing identity/directory tests |
| B08 | VM worker/scheduler/Nginx and encrypted-backup templates; health metadata; evidence-retention dry-run/holds/deletion replay; bounded scanner adapter; isolated encrypted SQLite restore and local HTTP load harness | OperationsTest, NotificationAndScanTest, executed restore/load rehearsals |
| B09 | Automated 12-lesson synthetic pilot and representative-pilot release checklist | PilotWorkflowTest; real calibration pending |

## Evidence recorded during implementation

- SQLite suite before the final notification/scanner additions: 86 tests, 430 assertions passed. Final totals are recorded after the final local/hosted runs below.
- MariaDB 11.4.13 before the final notification/scanner additions: 93 tests, 484 assertions passed, including concurrent worker, AI reservation and capacity tests. A MariaDB-specific migration error was fixed by preserving the foreign-key index before replacing the capacity uniqueness constraint.
- Chrome: six browser tests passed after extending the full coordinator/member flow through activation, autosave/reload, receipt, provisional grading, approval and mobile progress. Automated WCAG 2.2 AA checks passed on the exercised screens. This is not a complete manual accessibility audit.
- Isolated SQLite restore: five users, two enrolments, five identities, two private files and one outbox event; hashes/row counts matched, authenticated-encryption tampering rejected, a post-backup deletion reapplied, duplicate outbox replay produced one sink receipt. First successful measured drill: 2.938 seconds. This is not the production RTO.
- Local HTTP rehearsal: 100 synthetic members, 20 concurrent clients, 200 dashboard requests, zero failed responses; client p50 6,805 ms and p95 8,467 ms on the PHP development server/SQLite. **Production performance targets are not proven.** Report and harness preserve the runtime limitations.

## Remaining gates and limitations

Live OIDC/MFA and delegated identity remain unavailable. The primary-platform adapter deliberately makes no network calls until the real contract is supplied; promotion/feed/directory evidence is isolated. Provider HTTP adapters exist, but live models, accuracy/calibration, billing and data-handling policies are unqualified. SMTP acceptance is distinct from delivery; authenticated delivery/bounce callbacks await the chosen service. Scanner/ZipArchive qualification and automatic OCR/document extraction remain pending; manual assessment entry/confirmation is available. No host/offsite MariaDB+keys+IdP restore or representative human pilot has occurred.

The retention implementation removes eligible evidence objects with holds and a deletion ledger; it is not a complete subject-erasure implementation for all identity/grade/audit records. Actual retention obligations, hold release, subject-erasure approval and production ownership must be settled before live staff data. T26 design-package gaps remain separate. These limits prevent an honest claim that every production milestone is complete.

See [local workflows](LOCAL_WORKFLOWS.md), [deployment preparation](DEPLOYMENT_PREPARATION.md) and [pilot gates](PILOT_RELEASE_GATES.md).

## Final local verification, 3 October 2026

- SQLite: **92 tests / 467 assertions passed**.
- MariaDB 11.4.13: **96 tests / 500 assertions passed**, including concurrency scenarios.
- Chrome: **6 browser tests passed**, including mobile receipt/progress accessibility and the complete generation-to-reviewed-feedback journey.
- Pint, Composer strict validation, Blade compilation and JavaScript syntax checks passed.
- Latest isolated restore rehearsal passed in **4.014 seconds**; [restore report](evidence/restore-drill-2026-10-03.json). [Load report](evidence/load-rehearsal-2026-10-03.json) retains its limitations and measured p95.
- Hosted PR/CI evidence will be recorded after publication. No production-readiness claim follows from these local checks.
