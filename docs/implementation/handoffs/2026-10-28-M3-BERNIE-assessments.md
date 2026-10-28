# M3 assigned assessments — implementation and activation handoff

Date: 28 October 2026. Contributor: Bernie, explicitly confirmed in this session. User approved backend plus minimal usable UI and backed-up localhost activation. Feature branch: `codex/m3-assessments`; base revision `baaeaed757a2980271ba2991f2cbd2ee8f696c1b`. Changes are uncommitted; the final companion manifest records exact delivered hashes. Main branch and unrelated files are preserved.

## Delivered behavior

Reusable client-scoped assessment definitions publish immutable question/rubric versions. Assignments use the existing enrolment permission boundary without creating a programme or lesson. Members save/autosave responses, recover after reload or network failure, attach scanned private evidence and receive immutable submission receipts. Rejection permits a new attempt. Human scoring computes category/overall totals from a validated rubric, represents missing evidence as unavailable, and supports approve/modify/reject with immutable review lineage.

The existing gateway handles synthetic-only provisional analysis, with budget, idempotency, queue, provider and permission rechecks. Production mock/live AI remain blocked. Manual scoring works independently. Case-scoped alternate reviewers resolve appeals; private analysis/rationale never enters the member result. C03 v1 supplies the approved M4 handoff but does not create a development plan.

Uploads retain quarantine/scanning requirements. Extraction reuses the bounded runner; human confirmation and unchanged source hashes are required before file content can support a score. Deletion-ledger replay purges confirmed derived file text. No new scanner, provider, sandbox or SMTP service is provisioned, and no production file is marked clean by fixture tooling.

## Technical self-review

- Organisation/member/actor locks serialize mutations with permission changes; assignment versions reject stale edits and reviews. Review decisions bind the latest immutable attempt; duplicate submissions replay one receipt and mismatched idempotency conflicts.
- Definitions are scoped before retrieval. Published versions cannot be edited through the service. Client/team/member and sensitive-access checks remain server-side; administrative titles do not grant raw assessment access.
- Rubric criteria and evidence IDs are validated, weights total one, and application code calculates totals. Missing evidence cannot become zero. File IDs without confirmed content cannot justify scores. Manual overrides preserve the model proposal and record their source review.
- Appeals require an explicit Client Admin assignment to another eligible team coordinator. Authority is tied to the current role grant and case, and closes on resolution. Existing approved results remain visible while review is pending.
- Session-authenticated routes retain CSRF and selected-client checks. Member JSON/HTML projections omit private review history/reasons and provisional results. New content is escaped by Blade; no raw HTML or remote evidence fetches are introduced.
- The localhost profile stays loopback-only with external email and live AI disabled. Migration/activation must preserve existing rows and unrelated files, and private backup material is ignored rather than committed.

## Verification

Final execution evidence is recorded below. Earlier unsuccessful attempts are retained in ignored runtime evidence: an assignment default initialization issue and over-strict evidence validation were fixed; a fixture attempted a forbidden synthetic-status conversion and was corrected; overlapping test processes contaminated temporary file fixtures and were replaced with sequential runs. Browser evidence caught the hidden `action` field shadowing the form URL; this was fixed. Synthetic permission snapshots are refreshed only by a guarded disposable-browser helper; the production freshness rule is unchanged.

Browser acceptance covers authoring/publication/assignment, autosave/reload/network recovery, member receipt, rejection/resubmission, manual scoring, approved-result privacy, mobile overflow and keyboard focus. Axe found no serious or critical violations; existing moderate shared-layout landmark findings remain for UX review. Screenshots were visually inspected. This is automated/local evidence, not Saturnino's human review.

## Activation and rollback

The task-owned activation procedure verifies the exact port-8000 process and profile, stops only that runtime, archives the prior source, snapshots SQLite consistently, and copies the private profile/key/storage. It restores into an isolated candidate profile, applies the new migration, and compares every pre-existing table's row hashes. It then transfers only manifested source files, applies the same migration to the actual profile, verifies preservation again, restarts loopback port 8000 and checks login health. A failure restores the prior source/database and restarts the prior app.

The final activation result identifies the private ignored backup directory. Keep database, profile/key and storage together. For a later rollback, stop the verified M3 runtime, restore the prior source from the archive/file manifest and restore its matching database/profile/storage before restarting. Do not apply `migrate:fresh`, reseed, discard unrelated files, or expose a restored database before replaying applicable deletion ledgers. Coordinate later user writes before rolling back a snapshot.

## Remaining gates and handoff

Saturnino: review the authoring → assignment → member response/upload → review → approved result journey, including mobile/keyboard/error states, provisional labels and alternate-reviewer UX. Record findings against the manifest revision separately. Project owner: approve actual rubric content/thresholds and accept the milestone after reviewing the evidence. Neither acceptance is inferred here.

M1/M2 full human/live acceptance, real rubric calibration, scanner/extractor operational configuration, live AI, external email, Cerebru, M4 plan generation and hosted release remain separate. No remote push, PR publication, merge, paid service or external message occurred. A local PR draft and local Postman collection/examples are supplied; Postman execution and hosted CI are not claimed. Actual hours are unknown.

## Final execution evidence — 28 October 2026

- Affected regression suite: 87 tests reported, 86 passed, one skipped; 593 assertions, zero failures/errors. The skipped real-ClamAV integration check requires an unavailable scanner binary. Never treat this as clean-file production evidence.
- Disposable MariaDB: 18 tests / 94 assertions passed, including simultaneous submission/decision workers and migration rollback qualification. Disposable SQLite final retention/assessment regression: 15 tests / 74 assertions passed. These overlap the affected suite and must not be summed as distinct coverage.
- Browser: 11 checks passed, four screenshots; desktop/mobile, keyboard, autosave/reload/network recovery, submission receipt, rejection/resubmission and approved-result privacy. Axe had no serious/critical findings; moderate shared-layout landmarks remain. PHP formatting, changed-file whitespace checks, JavaScript syntax and local JSON artifacts validated.
- Evidence is in the companion `evidence/` directory. Postman examples were parsed but not executed in Postman. No hosted CI result is claimed.
- Activation succeeded from base `baaeaed757a2980271ba2991f2cbd2ee8f696c1b` plus the exact uncommitted files in `SHA256SUMS.json`. The feature remains on isolated branch `codex/m3-assessments`; the main checkout branch stays `codex/m2-local-production` with the activated changes. No commit, merge or remote publication occurred.
- Private verified backup: `.runtime/m3-activation-20261028-153551` in the main checkout. The SQLite backup and isolated restore/migration proof passed. All 61 pre-existing tables and 31 rows were preserved; all six new assessment tables were empty. Thirteen private storage files restored byte-for-byte; 29 unrelated files were preserved. The profile/key was verified by hash. Private backup contents are excluded from the handoff and version control.
- Thirty-four platform files were transferred and checked against source hashes. The production-profile additive migration passed; no synthetic seeding occurred. Loopback login returned 200 and unauthenticated assessments redirected to login (302). Authenticated full journeys were checked only in disposable synthetic profiles, not by altering the existing user's data. Live AI and SMTP remain disabled.
- Earlier activation attempts caught a missing CLI SQLite3 extension, an isolated Windows cache-path issue, and an unsuccessful launcher health check. The procedure restored the prior source/database after the failed health check. The final run explicitly enabled SQLite3 for backup and used relative cache paths plus a direct loopback health check. This supplies actual rollback evidence as well as the isolated restoration proof.
- Task-owned MariaDB on port 13319 was shut down after qualification. The intended port-8000 application remains running. Actual hours are not recorded.

Bernie's bounded technical implementation is ready for review and locally activated. Full M3 completion remains pending Saturnino's separate UX/accessibility review and project-owner acceptance; actual business rubric content, pass thresholds and live services remain unapproved. The review request above is a local handoff, not a message sent to Saturnino.
