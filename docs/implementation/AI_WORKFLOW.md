# AI session workflow

## Source and status rules

Read root AGENT.md first. Explicit user instructions take precedence. Use the requirements PDF for current product scope, recorded human decisions for resolved ambiguities, the named Markdown plan/charters for delivery, and compatible legacy domain specifications for precise existing safeguards. Code, tests and actual execution evidence establish status. Neither old milestones nor this planning package prove new milestone completion. Bernie is Developer A and technical lead; Saturnino is Developer B, UI and Design.

## Identify the requested operation

| Request | Default behaviour |
| --- | --- |
| Give an update / what is done | Read-only inspection and evidence-backed summary; no application tests, claims or file edits by default. |
| What is next / next step | Read-only selection of the smallest ready task and its execution prompt; do not begin implementation. |
| Record/update the status files | Write only supported state/evidence in owned planning files; no feature implementation is implied. |
| Implement/execute/continue a task | Work within the named developer/task scope, honour dependencies and record results; do not expand into later milestones. |
| Review a handoff | Review the exact supplied revision and PR diff, inspect relevant CI results, run authorised isolated checks when useful, and record findings; no automatic merge/release. |

Use explicit context to identify Bernie or Saturnino. For a general status request without identity, cover both lanes. Before claiming implementation work without identity, ask which contributor the session represents. Do not infer identity from machine path, Git author or account name. Bernie owns core implementation, checks and documented technical self-review. Saturnino may design and implement bounded UI slices only after the relevant shared contract is stable; his review covers UX and accessibility and is not technical approval. Project-owner acceptance is required for a locally completed milestone.

## Start-of-session checklist

1. Read STATUS.md, both developer files, the selected milestone/prompt, relevant decisions/contracts and current handoffs.
2. Inspect `git status --short`, branch and HEAD. Check code/evidence referenced by the ledger. Preserve unrelated changes and identify shared-file collisions.
3. Do not fetch/push silently merely to answer status. Report the locally known shared revision and any uncertainty. Synchronise through the team's agreed Git workflow when authorised; offline state cannot prove another developer is idle.
4. Resolve the task's entry gate and unaccepted contract decisions. Distinguish ready independent work from blocked dependent operations. Continue useful authorised work instead of stopping the whole milestone unnecessarily.
5. Before code changes, establish a task claim using COORDINATION.md. Use a dedicated short-lived branch or isolated worktree per feature/milestone task, an isolated database and the named contributor's state file. Deliver through a PR with required CI results, Bernie's documented technical self-review and applicable Saturnino UX/accessibility review as defined in COORDINATION.md. Project-owner milestone acceptance remains separate. No markdown file acts as a cross-machine lock.

## During implementation

- Inspect actual APIs/files and reuse existing services. Paths in prompts are ownership areas, not permission to overwrite whole directories.
- Keep client/team/member checks in shared server-side services. Test negative paths for reports/chat/files/jobs as well as screens.
- Generate only drafts/proposals for AI-dependent outcomes; humans approve official results and programme changes.
- Keep sensitive data, secrets and real user details out of logs, commits, fixtures and handoffs. Use synthetic records by default.
- Obtain specific recorded cost/data/live-operation authorisation before any corresponding side effect. Preparing a proposal is not approval.
- Test the change at the appropriate layer; no need to rerun the whole suite for a prose-only change. For application changes use the real command reference below, inspect current configuration and record passed/failed/not-run/blocked accurately.

## Evidence and completion

Evidence must include date, branch and revision (or explicitly dirty working tree), environment, exact command/scenario, result, artifact path and limits. A referenced historical result stays historical. Never infer actual hours from estimates or infer technical self-review, UX review or project-owner acceptance from silence.

Task states: `not_started`, `claimed`, `in_progress`, `blocked`, `ready_for_review`, `changes_requested`, `accepted`. Record blockers separately so partial progress remains visible. Technical readiness requires Bernie's recorded self-review tied to the exact revision and evidence. Applicable user-facing work also requires Saturnino's separate UX/accessibility review. `accepted` requires the project owner's decision tied to the exact revision and milestone evidence. A later material change invalidates the affected review and acceptance.

Milestone gates are independent: `local` and, where applicable, `live`. Use `not_assessed`, `partial`, `passed`, `blocked` or `not_applicable` with reason. Only the actual gate owner can record acceptance. M2 mock completion is `local_contract_complete`, never live completion. M7 requires the actual project owner's go/no-go; neither developer's AI can manufacture approval.

## End-of-session contract

Update only the current contributor's state and new task-specific handoff/evidence. Bernie owns shared rollups unless explicitly delegated. Include changes, exact checks, remaining gates, blockers, next ready step and the applicable technical, UX or project-owner action. Do not send a message to another person via external tools unless explicitly instructed. A saved handoff is the default communication artifact.

## Verified command reference

Run from `platform/`; these names were checked against the current README/configuration when the package was created. Recheck before use and verify the resolved database is isolated synthetic/test. Existing test commands can reset fixtures; never point them at shared development or production data.

```powershell
php artisan test --compact
php vendor/bin/phpunit -c phpunit.mariadb.xml
php vendor/bin/pint --test
npm.cmd run test:browser
```

Use a targeted `php artisan test --filter=ExistingTestName` for an affected real test after discovering its name. MariaDB checks require the isolated database described in `platform/README.md`. Browser tests use isolated `.runtime/browser.sqlite` and port 8123; parallel developers need separate paths/ports/configuration. Read `platform/scripts/restore-drill.php` and operations instructions before an isolated restore rehearsal. There is no application `npm run build` script in the reviewed package.json; do not invent one.

Fresh setup uses `composer install`, `php scripts/setup.php` and `npm.cmd ci` only as required and authorised; inspect scripts/configuration first. Do not use destructive reset/migrate-fresh commands on an existing shared database. Dependency installation, keys and worker processes must remain isolated and within task scope.
