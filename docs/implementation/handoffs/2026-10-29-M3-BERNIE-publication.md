# M3 PR and CI publication

The user authorised remote PR publication and CI on 29 October 2026 and explicitly directed skipping the task-ticket requirement for now. Merge and hosted deployment remain unauthorised. The previous handoff and SHA-256 manifest preserve the 28 October local activation snapshot; they are historical evidence, not a manifest of later publication metadata. Publication preparation normalized test imports and trailing whitespace in the draft script and migration-output copies; original activation evidence remains in the private local backup.

The isolated `codex/m3-assessments` branch was fast-forwarded to merged M2 base `eb6938a`. Its tree is identical to the tested `baaeaed` base. All 59 delivered manifest entries were checked before publication preparation. The existing production profile and unrelated main-checkout files are excluded from publication work.

CI runs the existing full SQLite and MariaDB suites (including M3 feature and concurrent-worker tests), document utilities and browser regressions. The browser job now also prepares its disposable browser database and executes the M3 authoring, assignment, draft recovery, rejection/resubmission and approved-result privacy journey on port 8127. M3 browser results are uploaded with the existing seven-day artifact retention. No real profile, private backup, live provider or SMTP credential is used.

Bernie's technical self-review covers the existing M3 handoff and this bounded CI wiring. Actual hosted results and the exact PR head will be recorded in the PR description after completion. Saturnino's separate UX/accessibility review and project-owner acceptance remain pending. No independent approval is implied.
