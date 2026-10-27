# M1 Bernie — branding and demo PR / CI

User authorised publication and hosted CI. Draft PR: https://github.com/scalyn-invited/Training-Assessment-Tracker-V2/pull/11, branch `codex/m1-saved-branding`, base `main` at `60354f0d647e6e9ed287ac9391309d7996c3b6dd`.

Initial published implementation: `438874b6acc2d5820393c5924de6deb81110a997`. Existing unrelated untracked files and local databases were preserved. No merge or deployment.

## Checks and correction

- Local `php vendor/bin/pint --test`: passed.
- Local `php -d extension=gd vendor/bin/phpunit --log-junit .runtime/shared-ports/pr-sqlite.xml`: 169 tests, 3,875 assertions, four skipped; no failures.
- Hosted run https://github.com/scalyn-invited/Training-Assessment-Tracker-V2/actions/runs/40449605295: PHP/SQLite, MariaDB/concurrent workers, and document parsing/OCR jobs passed.
- Browser job: 18 passed, one failed. The branding upload test's partial label selector matched both Brand colour and Brand colour hex value. Corrected it to exact-label matching; application behaviour unchanged. The separate realistic-demo browser step was not reached in this run.
- Follow-up hosted results will be recorded on the PR against its exact head revision. Do not interpret this receipt as a successful final hosted run.

## Technical self-review and boundaries

CI separates the realistic-demo browser fixture from the minimal UI fixture; each has its own disposable database. Reviewed the browser failure against its trace and changed only the ambiguous selector. Published implementation and prior feature self-reviews are documented in the saved-branding, automatic-theme, theme-defaults, realistic-demo and shared-demo-ports handoffs. Saturnino's independent UX/accessibility review, project-owner acceptance and full M1 acceptance remain pending. No live identity, paid service, original-database migration or production release is claimed.
