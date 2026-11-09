# Two-person coordination

The [feature checklist](FEATURE_CHECKLIST.md) is the shared development tracker. Work on one feature at a time, including implementation, QA, fixes, evidence and applicable acceptance before starting another. Check the latest available shared state before selecting work; a local checklist is not a distributed lock.

Contributor responsibilities do not require asking the requester whether they are Bernie or Saturnino. Do not make identity confirmation a prerequisite for authorised work. Use the configured Git author unless the user requests a different author; keep actual human review and approval attribution evidence-based.

Bernie owns core application, shared contracts, integration and technical checks/self-review. Saturnino owns UI/design and bounded presentation work and reviews applicable UX/accessibility. Coordinate shared layouts, routes, migrations, contracts, dependencies and other overlapping files before editing. Do not infer another developer is idle; use the team's authorised shared coordination channel and obtain acknowledgement for overlapping or shared-contract changes. If coordination is unavailable, do not make conflicting edits.

Use a dedicated short-lived branch or worktree per independently reviewable feature, with isolated databases, storage, workers and test runtimes where applicable. Preserve unrelated changes. Deliver through a PR with required CI results and Bernie's documented technical self-review; record Saturnino's UX/accessibility review when applicable. Project-owner milestone acceptance and live/production authorisation remain separate gates and must never be inferred. Reference actual reviews and acceptance in the checklist without duplicating them. Do not push, merge or deploy without authorisation.

Record concise blockers, results and evidence references in the checklist. Do not use developers/ or prompts/ for task tracking and do not create routine handoffs or duplicate status ledgers. Preserve existing historical records.
