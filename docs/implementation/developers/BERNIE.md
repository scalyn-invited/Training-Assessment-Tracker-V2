# Bernie developer state

Owner: Bernie — Developer A and technical lead. UI/design counterpart: Saturnino. This file is maintained by Bernie's session; Saturnino reads it and writes separate UI/UX review or handoff files.


The task-log estimates below are retained from the superseded baseline; they are not the current weekly resource allocation. Effective 23 October 2026, Bernie has 9h 36m per week: 8h 06m core development, technical checks and documented self-review plus 1h 30m coordination. See [the current plan](../IMPLEMENTATION_PLAN.md).
## Current task

### 2026-10-26 application permission integration claim

- Explicit user-approved implementation plan: integrate client context, scoped access, sensitive grants, appeal authority and all-session invalidation across existing consumers; Postman sandbox API collection included.
- Continuing preserved dirty branch `codex/m1-approved-client-foundation`, base `418dd7f`. Own backend/tests/routes and the specifically authorised minimal switcher/admin UI integration; no redesign or counterpart record edits. No active counterpart claim is recorded locally; remote activity remains unknown.
- Decisions: explicit multi-client choice; member own history retained within client; separate Client Admin grants sensitive access, no self-grants; grants end with relationships; appeal-specific alternate authority. All permission changes invalidate affected users' sessions.
- Only disposable SQLite/MariaDB databases; no application database migration, push, merge, paid service or deployment. Status: ready_for_review for the local permission-integration slice; full M1 remains in progress.
- Final complete SQLite: 145 tests / 801 assertions, 141 passed and four utility skips. Broad MariaDB: 163 tests / 922 assertions with four old teardown errors; corrected concurrency/cleanup recheck 20/20 passed (143 assertions), final permission/appeal/migration follow-up 20/20 passed (139 assertions). These are separate runs, not a claimed repeated full MariaDB pass.
- Postman v2.1 collection and empty environment validated; browser client selection/switching and synthetic grant/revoke checks passed. Mobile/desktop inspected; separate UX/accessibility review remains pending. Pint and whitespace checks passed.
- Original application database hash unchanged. Temporary MariaDB/browser services stopped and ports 13318/8123 confirmed closed. Changes remain uncommitted; actual hours unknown.
- [Delivery, exact revision/manifest, evidence, technical self-review and Saturnino review request](../handoffs/2026-10-26-M1-BERNIE-permission-integration.md). Application cutover, hosted CI, live identity and project-owner acceptance remain separate gates.

### 2026-10-26 approved mapping/backfill rehearsal claim

- Explicit user instruction: implement the approved isolated backfill plan. Both groups are teams; retain both primary coordinators; permissions start at the recorded rehearsal timestamp.
- Continuing the preserved dirty `codex/m1-approved-client-foundation` branch at `418dd7f5c2237d1c11d73bced4e6b11a26a4eca7`. Scope: uniquely named local rehearsal tooling, manifest, tests and evidence; no public API/consumer cutover or counterpart UI changes.
- Existing database is read-only. Only disposable SQLite copies and the verified task-owned MariaDB instance may change. No push/merge/deployment. No external coordination acknowledgement inferred.
- Status: ready_for_review for the completed local mapping/rehearsal slice; actual hours unknown. Full M1 remains in progress.
- Implemented versioned hash-bound manifest, guarded validation/apply tooling, atomic completion/audit ledger, idempotency and concurrency tests. Full SQLite source-copy rehearsal and pre-application snapshot restoration passed; no existing database migration/backfill.
- Final results: MariaDB-configured suite 148 passed / 4 utility skips, 843 assertions; SQLite suite 130 passed / 4 utility skips, 714 assertions. Both new MariaDB races passed. Pint/syntax/whitespace checks passed. Temporary server stopped and port closed; original hash matches capture.
- [Completion handoff and technical self-review](../handoffs/2026-10-26-M1-BERNIE-backfill-rehearsal.md), with [approved manifest](../handoffs/2026-10-26-M1-BERNIE-backfill-rehearsal/mapping-manifest.json) and exact evidence. Prior foundation source unchanged. No Postman collection because no public API was added.
- Next: coordinated application consumer integration and session invalidation qualification. Group/primary/date mapping decisions are now resolved for this source. No UI, hosted CI, project-owner M1 acceptance or live release is claimed.

### 2026-10-26 MariaDB qualification and mapping continuation

- Explicit user instruction: continue M1 with isolated synthetic MariaDB integrity/concurrency tests and a dry-run existing-data mapping report; do not migrate the application database, push, merge or deploy.
- Continuing `codex/m1-approved-client-foundation` at `418dd7f` plus preserved dirty foundation/proposal files. Scope: test harness, read-only mapping tooling/report, necessary fixes demonstrated by qualification, unique evidence/handoff. No UI/counterpart files.
- Existing SQLite eligibility check: five users, all `environment=test`, `is_synthetic=1`; inspection uses read-only SQLite connection. No assessment body output. Preserve database byte hash across inspection.
- Provision only a task-owned temporary database process/data directory, bind loopback, use dedicated database names and shut it down at completion. Existing XAMPP data/service remain untouched.
- Status: ready_for_review for this bounded local qualification/inventory slice. Full M1 and application cutover remain in progress. Actual hours unknown; no milestone acceptance or distributed lock inferred.
- Results: fixed MariaDB empty-rollback FK-index dependency; targeted 21/21 passed (117 assertions). Full MariaDB 132 passed / 4 utility skips (737 assertions); SQLite 123 passed / 4 utility skips (670 assertions); Python mapping 4 passed; targeted Pint and whitespace checks passed.
- Existing database byte hash unchanged; no mapping applied. Task-owned MariaDB 11.4.13 process shut down and port closed. Group classifications remain unresolved after the user requested team/cohort clarification. No push, merge or deployment.
- Evidence, technical self-review and next gates: [MariaDB qualification handoff](../handoffs/2026-10-26-M1-BERNIE-mariadb-mapping.md) and [mapping report](../handoffs/2026-10-26-M1-BERNIE-mariadb-mapping/MAPPING-REPORT.md). Next: approved mapping manifest and isolated populated backfill rehearsal; legacy consumer cutover still pending.

### 2026-10-26 approved foundation implementation claim

- Task: M1-BERNIE/approved-client-foundation; in_progress on `codex/m1-approved-client-foundation`, base `418dd7f`.
- Authority: current conversation architecture approval (`All approved`) followed by explicit confirmation that this is Bernie's session. The newer decisions supersede the pending D01/D02 proposals below; Cerebru remains deferred.
- Scope: additive client memberships, typed groups, scoped roles and dated primary/backup allocations; executable policy and administration services with isolated regression tests. Existing learning consumers retain their legacy boundary until an explicit tested cutover. No live database backfill, identity provisioning or production operations.
- Preserve previous proposal files, existing images and Word lock file. No Saturnino acknowledgement or remote activity is inferred; new foundation files are isolated from his UI lane. No external messages or remote push authorised.
- Database checks: in-memory SQLite and separately qualified isolated MariaDB only; never the existing application database.
- Unspecified notice duration, historical-access expiry and backup activation remain unset, not invented defaults.

### Approved foundation delivery

- New additive schema and Tenancy access/administration services implemented; see [handoff](../handoffs/2026-10-26-M1-BERNIE-approved-client-foundation.md) and [approved architecture decisions](../handoffs/2026-10-26-approved-architecture-decisions.md).
- Local component tested; full M1 remains partial. Legacy application consumers have NOT been cut over and no actual app database was migrated. Authentik, branding, standalone assessments and later training policies remain separate implementation work.
- Regression: 127 tests / 670 assertions / four documented external-utility skips; no failures. SQLite migration preservation rehearsal passed. MariaDB port 3308 unavailable; MariaDB/concurrency gate pending. Technical self-review recorded in handoff; human UX/implementation acceptance and PR/hosted CI not claimed.
- Branch remains uncommitted with prior changes preserved. No push, merge or deployment. No actual hours supplied.

- Updated: 2026-10-26.
- Task / slice: M1-BERNIE/C01-C02 synthetic fixtures and additive migration design; explicitly authorised by Bernie.
- Status: ready_for_review for this bounded proposal slice. D01/D02 block committing client/grant schema and backfill decisions; C01/C02 remain proposed.
- Branch / HEAD: codex/m1-bernie-c01-c02 at 418dd7f5c2237d1c11d73bced4e6b11a26a4eca7; branch created from the verified local HEAD.
- Dirty baseline: preserved untracked `docs/implementation/~$_ASSISTED_TWO_DEVELOPER_DELIVERY_PLAN.docx` and `platform/public/images/`; no task artifacts existed when this claim was recorded.
- Scope/ownership: Bernie-owned task-specific proposal artifacts only; no app source, migrations, shared contract registers or Saturnino files. No cross-machine lock or acknowledgement inferred.
- Prior M0 state: ready_for_review for its documentation/proposals; M0 gate partial and project-owner acceptance pending. See the unchanged [M0 handoff](../handoffs/2026-10-26-M0-BERNIE-reconciliation.md).
- Actual hours: unknown.

## Milestone task log

| Task | Estimate | Status | Evidence / reviewed revision |
| --- | --- | --- | --- |
| M0-BERNIE | 16h | ready_for_review | 2026-10-26 handoff; proposals/check evidence, four utility skips, MariaDB and human gates pending. |
| M1-BERNIE | 56h | in_progress | Historical Cerebru UI slice remains ready for review; current C01/C02 fixture/design slice is recorded in the [2026-10-26 handoff](../handoffs/2026-10-26-M1-BERNIE-c01-c02.md). Client foundation acceptance pending. |
| M2-BERNIE | 56h | in_progress | Local-password starter ready_for_review; [2026-10-27 handoff](../handoffs/2026-10-27-M2-BERNIE-local-password.md). Full M2/live/human gates remain open. |
| M3-BERNIE | 32h | not_started | None recorded |
| M4-BERNIE | 32h | not_started | None recorded |
| M5-BERNIE | 40h | not_started | None recorded |
| M6-BERNIE | 40h | not_started | None recorded |
| M7-BERNIE | 56h | not_started | None recorded |

## Session evidence and handoffs

For each task, append dated updates with revision/dirty state, bounded change, exact checks and result, handoff path, limitations, actual hours if supplied, and next dependency. Preserve previous records rather than rewriting history as complete.

## 2026-10-26 — M1-BERNIE/C01-C02 claim

- User explicitly authorised the ready fixtures and migration-design slice. Branch `codex/m1-bernie-c01-c02` starts at `418dd7f5c2237d1c11d73bced4e6b11a26a4eca7`; the two pre-existing untracked items above are preserved.
- D01/D02 remain open. This claim excludes schema/migration implementation, backfill, shared-register edits and permission-dependent release. C01/C02 fixtures and design remain task-specific proposals pending technical review and coordination.
- Saturnino's current remote activity is unknown; no UX/accessibility review is claimed. M0 history and project-owner acceptance are unchanged.

## 2026-10-26 — M1-BERNIE/C01-C02 proposal delivered

- Branch `codex/m1-bernie-c01-c02`; base HEAD `418dd7f5c2237d1c11d73bced4e6b11a26a4eca7`; changes remain uncommitted in the working tree. The two pre-existing untracked items remain untouched.
- Added logical synthetic C01/C02 scenarios and a migration/rollback sequence under [the M1 handoff directory](../handoffs/2026-10-26-M1-BERNIE-c01-c02/). Bernie technical self-review is recorded there.
- Artifact checks: fixture JSON parsed successfully (18 scenarios, 13 logical fixture entities); `git diff --check` passed for tracked changes; all handoff and contributor paths exist. No application, browser, MariaDB, or concurrency tests run; no app code/schema changed.
- D01/D02 and D03 remain open; no schema/backfill, shared-register change, UI binding, or M1 acceptance is claimed. Next: project owner records D01/D02 and actual source evidence/contact for D03; then coordinate the stable C01/C02 contract before implementation.

## Review and acceptance

For new work under the 23 October 2026 responsibility model, record Bernie's technical self-review against the exact revision, link Saturnino's separate UX/accessibility review when applicable, and leave milestone acceptance to the project owner. Historical peer-review requirements remain attached to the work created under the earlier model. Do not author Saturnino's findings or the project owner's acceptance.

## 2026-10-12 — M1-BERNIE/cerebru-ui

- User explicitly identified this session as Bernie and authorised the proposed Cerebru UI/theme/branding plan. This bounded instruction assigns Bernie the UI changes for this session; no Saturnino acceptance is inferred.
- Status: in_progress. Source branch feat/document-extraction; HEAD bdc55df0676258e941e3685540cecd6419fb9205; existing dirty layout/CSS/browser-test changes preserved.
- Managed worktree 71bb was created but is not writable by this session; development uses an isolated source copy at .runtime/cerebru-ui, with its own synthetic database, storage, and port 8137. Final transfer will verify source baselines before replacing touched files. Managed tool registered worktree 71bb; no source branch switch, commit, push or deployment.
- Scope: shared layout, public CSS/JS, dashboard, config-based branding resolver, appearance preview, meaningful UI/branding tests and documentation. No new client/grant schema or invented Cerebru identity API.
- Branding is deployment configuration plus authenticated existing organisation/environment overrides; this does not establish future client mapping or M1 acceptance. Persisted client-admin branding editor awaits accepted client contracts.
- Peer review pending; actual hours unknown.

## 2026-10-12 — UI slice verification and delivery

- Delivered 21 files to the original checkout after checking source SHA-256 baselines; exact delivered hashes are retained in the evidence directory. Existing dirty changes and Scalyn image assets were preserved. No database migration or source-database reset.
- 34 PHP tests passed (219 assertions in combined run); final portable branding-fixture refinement passed 5 tests/50 assertions. 10 browser scenarios passed, with five-theme contrast/accessibility, mobile navigation, preference isolation, preview, scoped downloads, receipts, onboarding, approval, learning and routing coverage. Six changed PHP files passed Pint; Blade compilation passed.
- Live identity/theme synchronisation, future client mapping, persisted client branding editor and full M1 gate remain unimplemented/unaccepted. No peer sign-off or production readiness claimed.

- Local runtime cleanup limitation: Windows denied stopping the task-created test servers on 8137–8139. Verify their current ownership before manual cleanup. The preview on 8140 is intentionally left running for review.

## 2026-10-13 — M1-BERNIE/workspace-ui

- User approved UI alignment to upskill.bernie.newwebsite.live/workspace and GitHub sync. Status: in_progress.
- Pulled origin/main with --ff-only: bdc55df -> de4c661; no incoming source differences. Existing 87 modified/untracked files backed up under .runtime/workspace-ui-20261013/baseline.
- Bounded shared UI assignment explicitly authorised to Bernie this session. Scope: layout, dashboard, CSS, default theme and affected browser tests. No contract/schema changes or counterpart acceptance inferred.
- Isolated candidate: .runtime/cerebru-ui; synthetic preview/test databases only. Source transfer requires baseline comparison.

## 2026-10-13 — Workspace UI delivery

- Eight UI/config/test/doc files delivered; preview at 8140. 34 PHP tests passed (220 assertions), Blade/Pint/diff checks passed.
- Browser suite: nine passed, one navigation timeout; unchanged focused rerun passed. Ten unique scenarios passed across both runs, original failure retained.
- Status: ready_for_review; peer/full-M1/production gates remain pending. Test servers 8141/8142 cleaned up.

## 2026-10-13 — M1-BERNIE/scalyn-branding

- User explicitly requested replacing Cerebru name/mark with supplied Scalyn logo and matching colours. Bounded branding follow-up in isolated .runtime/cerebru-ui; existing files backed up to .runtime/scalyn-branding-20261013.
- Status: in_progress. No schema, permission, provider or integration changes.

- Scalyn branding delivered: ten files, supplied logo unchanged, matching navy/blue palette and favicon. Status: ready_for_review. BrandingTest 5/50 passed; white-label preview and final five-theme browser checks passed after fixing early theme choice/reset handlers. See 2026-10-13-M1-BERNIE-scalyn-branding handoff.

## 2026-10-13 — UI PR and CI

- User requested PR and CI. Branch feat/scalyn-workspace-ui; commit 54a1c73, 22 UI/branding files. Pushed and opened PR #6: https://github.com/scalyn-invited/Training-Assessment-Tracker-V2/pull/6.
- Existing four-job Training Platform CI triggered; results pending. Unrelated root guidance/planning documents remain local and uncommitted. No merge or deployment.

- PR #6 CI run 38354816274 passed all four jobs on 54a1c73: PHP/SQLite, MariaDB/concurrent workers, browser/accessibility and document parsing/OCR. Ready for peer review; not merged or deployed.

## 2026-10-26 — M0-BERNIE claim

- User Bernie explicitly authorised the M0 reconciliation plan. Status: in_progress.
- Branch: codex/m0-bernie-reconciliation; baseline HEAD: 8ce63129b5985c6edd86361df863ed960c999bfc.
- Scope: unique M0 evidence/proposal files and this developer record only. No feature changes.
- Saturnino's remote state is unknown; no shared acknowledgement. Shared registers and Saturnino's record are held unchanged.
- Isolation: .runtime/m0-bernie-20261026; preserve untracked public images and Word lock file.
- Next checkpoint: source coverage, isolated technical/browser results, unfrozen proposals and technical self-review.
- Actual hours unknown; milestone/project-owner acceptance pending.

## Previous current-task snapshot (preserved 2026-10-26)

- Updated: 2026-10-13.
- Task / slice: M1-BERNIE/workspace-ui, explicitly approved by user Bernie.
- Status: ready_for_review; full M1 foundation remains pending.
- Branch / HEAD: feat/document-extraction at de4c66197db5e6b474cf588dc427633a43a6c6c9 plus preserved local changes; pulled latest origin/main, no commit/push.
- Isolation: .runtime/cerebru-ui, synthetic databases; eight files delivered after baseline comparison.
- Evidence: ../handoffs/2026-10-13-M1-BERNIE-workspace-ui.md and dated evidence directory.
- Next checkpoint: Saturnino reviews exact delivered manifest and visual/test evidence. No shared UI edits remain reserved by this session.
- Actual hours: unknown.

## 2026-10-26 — M0-BERNIE delivery

- PR #9 opened against main; hosted CI run 40264329243 passed PHP/SQLite, MariaDB/concurrency, browser/accessibility and document parsing/OCR on commit b6c6dc6. Record-only follow-up is pending its own run. PR remains open; no merge or milestone acceptance.

- Completed the bounded documentation/proposal lane; no feature implementation or shared-register edit. Source sections 1-37 and V2-01-V2-15 mapped, eleven local backlog tickets, ten open decision packets and seven unfrozen contract proposals prepared.
- Fresh isolated setup, PHP suite (107 passed, four skipped, 587 assertions), seven browser scenarios and read-only Pint passed. Restricted-runner/bootstrap and environment setup failures retained; successful retries are separately identified. MariaDB/concurrency blocked; live/utility qualification remains incomplete.
- Technical self-review recorded in the separate verification file against source HEAD and delivery manifest; commit 6f1dafa pushed to origin. Saturnino UX review and project-owner acceptance pending; no actual hours supplied.
- Historical branding handoff differs from present HEAD; integration remains unverified. Preserve all unrelated files and prior M1 review history.

### 2026-10-27 functional UI claim
User assigned Bernie all existing screens, shared layout/styles/navigation for this pass; Saturnino follows afterward. Base 418dd7f5c2237d1c11d73bced4e6b11a26a4eca7 with all prior dirty changes preserved. Branch codex/m1-functional-sidebar. Separate UI database/port; no production migration or push. Status: ready_for_review for this local UI slice.

- Final verification: 12 browser tests passed; 79 PHP tests / 444 assertions passed; Pint, JS syntax and whitespace checks passed. Initial browser run had six failures, corrected and retained in the evidence; no silent pass claim.
- Shared sidebar/header/styles cover existing screens. Desktop/mobile screenshots inspected; Saturnino UX/accessibility review, hosted CI and full M1 acceptance remain pending.
- Application database SHA256 unchanged from this pass's baseline. Existing Postman preview remained active; its session/cache writes changed its byte hash, and it was not reset/migrated. Separate UI fixture and preview on port 8124.
- [Handoff, exact source hashes, checks and screenshots](../handoffs/2026-10-27-M1-BERNIE-functional-ui.md). Changes remain uncommitted; no push, merge, deployment or paid service. Actual hours not supplied.

### 2026-10-27 combined M1 PR / CI publication

User explicitly authorised combined M1 publication and hosted CI, then selected preservation of main's Scalyn branding/themes while integrating the sidebar. Combined commit c79ca5b; integrate main 9d794e0 without merging the PR. Fresh branding/permission/foundation PHP checks: 59 tests / 353 assertions passed. Expanded browser and hosted CI results pending. [Publication handoff](../handoffs/2026-10-27-M1-BERNIE-pr-ci.md). Earlier green UI evidence is historical, superseded by the selected Scalyn integration.

- Draft PR #10 published: https://github.com/scalyn-invited/Training-Assessment-Tracker-V2/pull/10. Integrated head 3110fb1 was mergeable. First CI 40431145801 passed MariaDB and document utilities; PHP formatting failed and was corrected. Browser final result pending. Local expanded Chrome run was interrupted after timeouts/session closure; earlier green-only 12/12 is not an integrated Scalyn pass.

- First hosted browser suite: 14/16 passed; fixed a hidden-theme-option focus-loop bug and a now-ambiguous legacy test selector. Both affected mobile scenarios passed locally afterward. Current integrated Scalyn screenshots and PHP/mobile test reports are in the PR/CI handoff evidence folder. Final hosted head still requires its own CI result.

## Final hosted CI receipt — 2026-10-27

PR #10: https://github.com/scalyn-invited/Training-Assessment-Tracker-V2/pull/10. Current verified head `908b6c5c5c91b34552fc4e41c284e9e25b047359`. All four jobs in run `40432225153` completed successfully: PHP/SQLite, MariaDB/concurrent workers, browser/accessibility and document parsing/OCR. https://github.com/scalyn-invited/Training-Assessment-Tracker-V2/actions/runs/40432225153

GitHub reported the PR mergeable and no longer draft at final verification; this session did not merge it or claim a reviewer's acceptance. The final result is also published in the PR description. This local documentation receipt does not change the tested source revision. No migration of the existing application database or deployment occurred. The original database hash remains 9EADDD403FF6087873072572B610AC5F0B0DC96F9E944D5A6FE1523B2FE309A9. The isolated Scalyn preview is running on 8124 (PID stored in .runtime/ui-sidebar/preview.pid); no MariaDB or queue worker was left running locally by this pass.

## 2026-10-27 — M1 saved branding claim

User approved implementation of saved client branding and one private raster logo, immediately published per selected client. Branch codex/m1-saved-branding, starting revision 908b6c5c5c91b34552fc4e41c284e9e25b047359. Existing local receipts and untracked files preserved. Bernie owns service/routes/migration and this explicitly requested UI integration; Saturnino UX review remains separate. Isolated databases only; no original or Postman migration.

## 2026-10-27 — Saved branding verification

Implemented client-scoped branding saves/private raster logos on codex/m1-saved-branding (uncommitted, base 908b6c5). SQLite and MariaDB: 23 tests/208 assertions each; concurrency: 8/50; MariaDB history migration: 1/14. SQLite migration preserved 55 legacy table hashes and original application hash unchanged. Browser: initial four pass plus corrected final upload/save scenario pass; not a new combined-suite claim. Temporary MariaDB stopped. Synthetic preview port8125; Alex -> Appearance. Technical self-review, source hashes, screenshots, failures/corrections and outstanding Saturnino/owner review in [saved-branding handoff](../handoffs/2026-10-27-M1-BERNIE-saved-branding.md). No push/deployment/original or Postman migration.

## 2026-10-27 — Existing UI preview save repair

Reproduced missing-branding-table failure on8124. Backed up synthetic UI DB and applied only branding migration there; original/Postman unchanged. Restarted verified preview with GD, added startup readiness and missing-schema regression. Actual in-app save, reload persistence and original-name restoration passed; 14 tests/147 assertions passed. See [repair evidence](../handoffs/2026-10-27-M1-BERNIE-branding-save-repair.md).

## 2026-10-27 — Client theme continuation

User confirmed four individually editable colours (primary/background/card/text), as a selectable Client theme alongside the five existing themes. Continuing the uncommitted saved-branding slice on codex/m1-saved-branding, preserving earlier changes. Client/environment storage and contrast validation; isolated preview migrations only. No implicit change to any member’s selected theme.

## 2026-10-27 — Client theme verified

Four editable Client theme colours and opt-in sixth theme delivered locally. SQLite29/271, MariaDB31/280 (including concurrency/migration), browser6/6 passed after fixing preview paragraph contrast. Both synthetic previews backed up/migrated; existing domain data preserved. Original application file changed at the final check: only sessions/cache differ from the preserved source, no new tables/migrations; see handoff investigation. Temporary test/MariaDB servers stopped. Current8124 branding and personal preferences unchanged. Technical self-review, exact source hashes, screenshots and outstanding Saturnino/owner review in [client-theme handoff](../handoffs/2026-10-27-M1-BERNIE-client-theme.md). No publication/deployment.

## 2026-10-27 — Automatic brand palette continuation

User approved one Brand colours picker/hex input, generated Client Light/Dark and conversion of existing manual palettes only on next explicit save. Continuing codex/m1-saved-branding without discarding existing changes; no schema migration required. Preserve current8124 branding and preferences until user saves.

## 2026-10-27 — Automatic brand palettes verified

One Brand colours picker/hex now generates Client Light/Dark; manual palettes remain unchanged until explicit save. Source/version saved in existing JSON, stale four-colour forms rejected, built-ins and scoped permissions preserved. SQLite30/3043, MariaDB31/3050 including concurrent saves, browser6/6 and16 parity fixtures passed. Domain hashes match original application47 tables and both existing previews54 each; sessions/cache excluded. No migration or Postman mutation. Temporary MariaDB/browser services stopped; existing8124 preview remains available with saved branding unchanged. See [automatic-theme handoff](../handoffs/2026-10-27-M1-BERNIE-automatic-theme.md) for exact source hashes, commands, screenshots, self-review and remaining Saturnino/owner/CI gates. No push, merge or deployment.

## 2026-10-27 — Client defaults and softer borders

User-approved continuation on codex/m1-saved-branding, base908b6c5c5c91b34552fc4e41c284e9e25b047359. Added client default plus account/client/environment preferences, explicit legacy-choice decision, soft decorative borders with separate control boundaries. Existing changes preserved. SQLite33/3078 and MariaDB35/3092 passed; migration1/3 and extra border checks2/2793 passed. Synthetic8124 backed up before additive migration; application/Postman domain hashes unchanged. Browser qualification found shared throttle budget and was corrected with a separate personal-preference budget; final browser evidence pending below. No publication or deployment.

Theme-default verification closed locally: initial browser6/7, corrected throttle scenario1/1, final cross-device1/1 after resetting test decisions. All scenarios now have passing evidence across runs; no combined rerun claim. [Handoff and self-review](../handoffs/2026-10-27-M1-BERNIE-theme-defaults.md) records failures, exact files, screenshots, data integrity and remaining Saturnino/owner/CI gates.

## 2026-10-27 — Realistic demo factories and separate preview

User-approved continuation on codex/m1-saved-branding, base908b6c5. Added opt-in guarded demo seeder, explicit scoped factories and service-built workflows for3 fictional clients,30 people and21 enrolments. Preview8129 is separate from unchanged8124/application/Postman data. SQLite demo1/30 plus regressions32/200, MariaDB33/229 and browser3/3 passed; initial missing review-request transition corrected and recorded. Identical final seed rerun adds nothing. MariaDB/test browser stopped. [Technical self-review, exact source/evidence and handoff](../handoffs/2026-10-27-M1-BERNIE-realistic-demo.md); [sign-in and scenario guide](../handoffs/2026-10-27-M1-BERNIE-realistic-demo-guide.md). Saturnino/owner/CI/full M1 gates remain separate. No push, merge or deployment.

## 2026-10-27 — Shared demo ports and8000 repair

User approved shared realistic-demo data on8000/8123/8124/8125/8129. Replaced verified runtime instances with one guarded multi-port launcher; old databases backed up and domain hashes unchanged. Port8000 failed on missing role_assignments in the preserved original database; runtime now points at the qualified demo. Authenticated Chrome and synthetic API checks returned200 on all five ports. No migration/reseed/push/deployment. [Cutover evidence and restart instructions](../handoffs/2026-10-27-M1-BERNIE-shared-demo-ports.md).

## 2026-10-27 — Branding/demo PR and hosted CI

User authorised publication. Draft PR #11 publishes saved branding, account themes, realistic fixtures and shared-demo launcher. Local SQLite169/3875 with4 skips and formatting passed. Initial hosted run passed SQLite, MariaDB/concurrency and document tools; browser18/19 exposed an ambiguous colour-label test selector, corrected to exact matching. Final hosted results are recorded on the PR. See [PR/CI handoff](../handoffs/2026-10-27-M1-BERNIE-branding-pr-ci.md). Saturnino/owner/full M1 acceptance remain pending; no merge/deployment.

## 2026-10-27 — Global Admin workspace claim

M1-BERNIE/global-admin in progress on codex/m1-global-admin, starting bdd55830adaceb241d48c5965f3999a88ddd4a03. User explicitly approved platform administration, role provisioning, suspension, platform defaults and synthetic demo upgrade, including shared navigation integration. No remote publication authorised. Preserve unrelated files; no Saturnino record edits. Qualification uses disposable databases; shared demo backup precedes upgrade.

Global Admin slice locally ready for review: dedicated platform UI, environment-scoped client suspension, audited bootstrap/role changes, last-active-admin concurrency protection and saved platform defaults/logo. Shared synthetic demo upgraded after backup; Alex Rowan fictional Global Admin works on all five ports. Application/Postman domain hashes and all pre-existing demo rows/branding preserved; restored snapshot verified. Full SQLite176/3948 with4 skips; final platform10/98; MariaDB combined22/171; final current-read concurrency4/24 and SQLite permission20/164 passed. Ten desktop/mobile screen checks had no axe violations or overflow; ordinary Client Admin denied403. Temporary services stopped. [Exact source hashes, commands, failures, self-review, screenshots and Saturnino handoff](../handoffs/2026-10-27-M1-BERNIE-global-admin.md). No push, merge, deployment or hosted CI; Saturnino/owner/full M1 acceptance pending.

## 2026-10-27 — Global Admin publication and CI

User now authorised PR publication and hosted CI. Previous PR #11 is merged at f1b38651cc57c418029a9894750caa85dee62e6d; the Global Admin branch fast-forwards to that identical-content base. CI now upgrades its isolated realistic-demo fixture and runs Global Admin desktop/mobile accessibility, navigation and Client Admin denial checks alongside the existing journeys. Hosted results will be recorded against the exact PR head in its description. No merge or deployment authorised; existing synthetic services/databases preserved.

## 2026-10-27 — M2-BERNIE/local-password starter claim

User explicitly requested the approved Bernie-led M2 starter plan. Branch `codex/m2-local-identity`, isolated checkout `.runtime/m2-starter`, base `6e2b9f5`. Status: in_progress. Scope: local synthetic password authentication, account setup/recovery, consent-based Member invitations, minimal presentation wiring, tests, and Cerebru discovery preparation. Authentik and live Cerebru are deferred. Existing database is untouched; disposable SQLite/MariaDB only. No counterpart acknowledgement or review is inferred; Saturnino's record is not edited. Shared integration is isolated; no push, merge, real data or deployment authorised.

## 2026-10-27 — M2 starter local delivery

Local-password starter is ready_for_review in the isolated `codex/m2-local-identity` checkout, base `6e2b9f5` plus uncommitted changes. Exact manifest, technical self-review, SQLite/MariaDB race results, browser evidence and remaining gates are in the dated M2 handoff. Existing application database unchanged. No real data, external integration, paid usage, push/merge or deployment. Saturnino review, project-owner acceptance, PR/hosted CI and full M2 remain pending. Authentik hosting and implementation deferred by explicit user instruction; Cerebru discovery inputs unavailable. Actual hours unknown.

## 2026-10-27 — Main-checkout M2 activation

Explicit user-approved transfer to `codex/m2-main-local` completed after verifying the 52-file source manifest. Fresh synthetic database prepared; original database and 29 unrelated files verified unchanged. Main-checkout targeted tests: 33 passed / 272 assertions. UI refinement by Saturnino is not a local-use gate per current user instruction. Runtime activation awaits the user's choice about an existing grouped preview launcher owning port 8000 and four other ports; do not treat the old preview as M2. See [activation handoff](../handoffs/2026-10-27-M2-BERNIE-main-activation.md). No remote push, real data, paid services or deployment.

## 2026-10-28 — Main M2 preview active

User authorised replacement of the old grouped previews. M2 now runs on port 8000 from codex/m2-main-local using the fresh synthetic database. Main browser flows, supplied demo credentials, client selection and protected-route denial passed. Original database and 29 unrelated files unchanged. See the activation handoff for evidence and rollback. Saturnino refinement and human milestone acceptance remain pending; local preview is available now.

## 2026-10-28 — M2 local-development accounts

User authorised implementation of the local-development restriction removal plan. Claim: codex/m2-local-development, base 6e2b9f5 with preserved uncommitted M2 work. Scope: local account classification and ordinary email addresses, bounded admin UI, disabled-by-default outbound configuration and account mail preparation, tests and handoff. Existing application database is excluded. No provider selection, paid calls, external emails, deployment or human review inferred.

## 2026-10-28 — Local-development account delivery

Ordinary email addresses and explicit Test account choice implemented on codex/m2-local-development. Non-test local account lifecycle works; existing accounts are not converted. SMTP and AI configuration prepared, disabled pending the user's unresolved provider/recipient/data/budget decisions. 59 tests / 418 assertions passed; browser setup/login/client selection/denied admin access and keyboard/mobile form checks passed. Original database and 29 unrelated files unchanged. Technical self-review and activation/rollback details: [handoff](../handoffs/2026-10-28-M2-BERNIE-local-development.md). No external email, paid AI, live Cerebru, deployment or human acceptance claimed.

## 2026-10-28 — Localhost production profile claim
User approved an empty production-partition database, ordinary account UI and Bernie Santos Cabase as first Global Admin. Branch codex/m2-local-production, base 6e2b9f5 with preserved prior work. Existing preview stopped after ownership verification; both databases frozen and hashed. No external services, workers, real-password selection or hosted release authorised. Tests use disposable databases; Saturnino review remains separate.

## 2026-10-28 — Localhost production profile delivery

Localhost production runtime delivered on codex/m2-local-production with a separate empty database and the explicitly named Global Admin awaiting private password setup. Existing databases and unrelated files preserved. 63 tests / 474 assertions passed, followed by 27 tests / 171 assertions after the final mock-AI guard; browser empty workspace/client creation/account setup/keyboard/mobile/access checks passed in a disposable rehearsal. Port 8000 left running. Technical self-review, private configuration, reissue and rollback instructions: [handoff](../handoffs/2026-10-28-M2-BERNIE-local-production.md). No hosted deployment, MFA completion, SMTP, live AI or Cerebru acceptance claimed; human review gates remain pending.
