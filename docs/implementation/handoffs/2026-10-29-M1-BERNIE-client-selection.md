# Platform and account client-selection fix

Date: 29 October 2026 (Asia/Manila). Contributor: Bernie. Status: ready_for_review.
Branch: codex/fix-platform-client-selection. Base HEAD: baaeaed757a2980271ba2991f2cbd2ee8f696c1b. Delivery is uncommitted; exact source hashes are in [source-sha256.json](2026-10-29-M1-BERNIE-client-selection/source-sha256.json).

## Implementation

The existing-account client dropdown previously selected the first alphabetical client and used only the current page of client options. It now defaults to the target account's sole active membership. Multiple or absent memberships require an explicit choice. Options are independent of client-list pagination and environment-scoped; inactive/suspended clients are excluded. No implicit membership is created.

Platform account actions, account creation/invitation and setup/recovery use empty choices when there is no existing value. Saved appearance themes and account-wide recovery remain intentional defaults. Hidden form identifiers isolate validation recovery to the submitted form. Platform account errors retain account-list pagination/search, reopen the account and retain its client/action/reason. Passwords are not flashed. Missing membership becomes an actionable validation error with invitation-acceptance guidance in password mode. JSON validation remains 422; stale version conflicts remain 409. Permission, invitation, audit and transaction rules remain unchanged.

No schema/migration, route, new public API endpoint, paid service, remote push, PR, merge, deployment or actual account update was performed. Existing application databases were not used for verification. Only the eight source/test files in the manifest, this evidence and an append to Bernie's contributor record were intentionally edited. Existing uncommitted M3 work remains in place. A temporary pre-edit hash manifest was unavailable at final verification: preservation is supported by the edit boundary and diff review, not a completed before/after hash comparison.

## Verification

Run from platform/:

- php -d extension=gd vendor/bin/phpunit --filter='PlatformAdministrationTest|LocalAccountsTest|ClientFoundationTest|ClientPermissionIntegrationTest' --log-junit .runtime/platform-selection-tests.xml: **56 tests, 450 assertions passed** using in-memory SQLite. [JUnit](2026-10-29-M1-BERNIE-client-selection/tests.xml).
- php vendor/bin/pint --test with the five changed PHP files explicitly supplied: passed.
- git diff --check: passed; existing line-ending notices only.
- Chrome/Playwright CLI against disposable .runtime/platform-selection/test.sqlite on port 8136: confirmed sole membership beyond client pagination, no-client/multiple-client placeholders, wrong-client validation with retained selections, unaffected neighboring form, successful corrected grant, creation placeholder, recovery placeholders/account-wide recovery and appearance Light default.
- Keyboard Tab from recovery Action moves to Account; visible focus inspected. Mobile viewport 390 x 844 had document width 390, no horizontal overflow. [Screenshot](2026-10-29-M1-BERNIE-client-selection/recovery-mobile.png). Snapshots are alongside this handoff.
- Verification browser and disposable server stopped. No external email or AI calls.

Initial checks exposed and resolved a Blade inline/block PHP compilation interaction. Default PHP lacked GD; the final suite explicitly enabled the existing extension. Restricted-runner dependency access required local execution approval. The initial browser seed failed its mock-identity guard; the disposable fixture was corrected to seed in mock mode before switching to password mode. These initial failures are distinct from final passing results.

## Technical self-review and remaining gates

Bernie technical self-review (agent-assisted, not independent approval): source diff reviewed for membership/permission/version/audit preservation, isolation of submitted form values, all-client option coverage, credential flash exclusions and bounded scope. Existing denial tests and new regression cases passed. No migration/data rollback is required; reverse only this task's source changes using the manifest/diff while retaining M3 work.

No MariaDB concurrency rerun: database locking behavior was unchanged. Browser checks are focused functional/keyboard/mobile checks, not a full accessibility audit. Saturnino UX/accessibility review, owner acceptance, hosted CI and publication remain separate and unclaimed.

Browser scripts are evidence copies: restore them under platform/.runtime/platform-selection/fixture.php and start.cjs for their relative paths. They use synthetic credentials only; the fixture guards its database path and test environment.
