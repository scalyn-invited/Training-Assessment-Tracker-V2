# Two person coordination

## Capacity and cadence

Effective 23 October 2026, the weekly planning target is 12 project hours. Bernie (Developer A and technical lead) receives 9h 36m: 8h 06m core development, technical checks and documented self-review plus 1h 30m coordination. Saturnino (Developer B, UI and Design) receives 2h 24m: 2h flexibly divided between design and small approved UI implementation, including usability/accessibility checks, plus 24m coordination. Across two to three weeks, that totals 24–36 project hours: 20h 12m–30h 18m development/design and 3h 48m–5h 42m coordination. Bernie receives 19h 12m–28h 48m and Saturnino receives 4h 48m–7h 12m.

The AI-assisted build target remains 3–5 M0–M7 work packages reaching local acceptance each week, or about 2–3 weeks from an agreed kickoff. This target is provisional and unvalidated. Reforecast at the end of week one; if fewer than three work packages have reached local acceptance or a critical dependency blocks the pace, revise the target. The old 640 implementation-hour split (Bernie 328, Saturnino 312) and 28-week/fourteen-iteration schedule are superseded estimates that already included AI assistance, not actual hours. Do not assume overtime or count live integration, pilot observation, the full 30-day learning cycle or production release inside the 2–3-week build target.

## File and module ownership

| Area | Primary writer | Counterpart responsibility |
| --- | --- | --- |
| Client/grant schema, Access, identities, migrations and shared policy fixtures | Bernie | Saturnino consumes stable UI contracts and reviews isolation messages/UX. |
| Cerebru adapters, gateway, calendar/approval domain, report projections and exports | Bernie | Saturnino designs the related UI states and may implement bounded presentation slices. |
| Client/assessment/plan/progress/notes/report/chat views | Saturnino for designs and approved UI slices; Bernie for service wiring and any policy-bearing code | Saturnino reviews usability/accessibility; Bernie records technical checks and self-review. |
| Automated domain, feature, integration, concurrency and security checks | Bernie | Saturnino may add UI/browser coverage only for his bounded slice. |
| Routes, dependencies/lockfiles, common configuration and CI | Bernie coordinates one named writer per change | Agree paths and order before editing; no simultaneous lockfile updates. |
| Shared layout/CSS/navigation | Saturnino coordinates design and bounded UI changes; Bernie coordinates integration | Preserve existing work and agree integration points. |
| STATUS, DECISIONS, CONTRACTS, REQUIREMENTS, COST_APPROVALS and root AGENT | Bernie maintains rollups | Saturnino proposes changes in a task handoff or owns them only after explicit delegation. |
| developers/BERNIE.md | Bernie | Saturnino reads; no overwriting. |
| developers/SATURNINO.md | Saturnino | Bernie reads; no overwriting. |
| Handoff/evidence files | Task author writes a unique file; Bernie records technical self-review; Saturnino records applicable UX review | Preserve originals and link exact revisions; project-owner acceptance is separate. |

Ownership coordinates edits; it is not a product permission role. Do not implement app access by contributor name. Saturnino's review is limited to UX and accessibility and is not independent technical approval. Bernie performs documented technical self-review. The project owner provides final product acceptance for locally completed milestones.

## Claiming a task safely

1. Inspect latest locally available shared state and both developer records. If another machine's state is unknown, do not assume its shared files are free.
2. Use task ID `M<n>-BERNIE` or `M<n>-SATURNINO`; append a bounded slice such as `/client-grants` when needed. Create a dedicated short-lived branch or isolated worktree for that independently reviewable feature/milestone task. Record scope, branch/worktree, HEAD, files/contract versions, status and next checkpoint in your developer file.
3. Publish the claim through the existing authorised shared Git/issue workflow before editing overlapping files. Require an explicit recorded acknowledgement for overlap or shared-contract changes. Do not automatically post messages or push when not authorised.
4. If no shared coordination channel is available, work on non-overlapping documentation, fixtures or owned files in isolation. Hold conflicting changes until ownership is established. A local claim or elapsed time is not a lock/acknowledgement.
5. Reserve unique migration timestamps and let Bernie sequence migrations. Each developer uses a distinct database, private files, queue worker and browser-test runtime/port.

## Handoff and review

Use [handoff template](templates/HANDOFF.md); save as `handoffs/YYYY-MM-DD-Mn-DEVELOPER-short-slug.md` (add a time/suffix if needed). Reference commit/branch, actual checks, source decisions and contract versions. Open a pull request linked to the task ticket for each feature/milestone branch; include acceptance evidence, relevant CI results and the exact revision. Required CI checks must pass before merge unless an explicit exception is recorded. Bernie reviews the diff and checks, records a separate technical self-review, and cannot use self-review to claim independent approval. Saturnino records a separate UX/accessibility review when the change affects user-facing behavior. The project owner records milestone acceptance; neither contributor may fabricate it.

Contract change sequence: proposal → Bernie's technical self-review → recorded stable version → Saturnino's bounded UI consumption → integration evidence → project-owner milestone acceptance. Saturnino may prepare designs and fixtures against a clearly provisional interface but cannot call it frozen or accepted. Do not silently broaden a schema to fix one screen.

Before integration, refresh from the agreed shared branch when authorised, reconcile the named shared-file owner’s changes and run affected checks. Do not resolve a conflict by discarding the other contributor's work. Merge/push/release only when authorised by the user or existing session scope. Technical self-review, UX review and local product acceptance are not production permission.

## State reconciliation

The contributor files plus exact code/review evidence are the freshest task evidence. STATUS.md is a rollup that may lag. Bernie reconciles technically self-reviewed work, applicable UX reviews and project-owner acceptance into the rollup without overwriting unreviewed work. If sources disagree, report the disagreement and classify it as unverified; do not pick whichever looks most complete. Preserve an append-only concise change log in each state file.
