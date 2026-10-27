# M1-BERNIE: Branding save repair on the existing UI preview

User reported inability to save. Reproduced in the actual in-app browser at http://127.0.0.1:8124/settings/appearance: generic save failure. Current log confirmed missing `client_brandings` table in `.runtime/ui-sidebar/ui.sqlite`. The earlier completed branding checks used port8125's separately prepared database; port8124 was not upgraded. Initial sign-in also had an expired CSRF form; loading a fresh sign-in form resolved that separate stale-page issue.

Verified the UI database contained only synthetic users, created a SQLite backup at `.runtime/branding/save-repair/ui-before.sqlite`, and applied only `2026_10_27_100000_create_client_brandings_table.php` using the explicit UI fixture environment. Restarted the verified task-owned PHP server on8124 with GD enabled; no machine PHP setting changed. `scripts/ui-preview.mjs` now checks branding schema/GD readiness before serving, rather than allowing a broken preview to start. Branding save also returns an actionable setup validation error when its table is missing.

Actual browser verification: saved original settings; changed application name to a temporary save-check label; reloaded and confirmed persisted name/title; restored the original name and saved successfully. Screenshot included. This live repair test used no logo upload; prior upload tests plus the current 14-test suite cover logo behavior, and the8124 server now loads GD.

`php -d extension=gd vendor/bin/phpunit --filter 'SavedBrandingTest|BrandingTest'`: **14 tests / 147 assertions passed**, including missing-schema regression. Pint and JS syntax checks passed. Original application SHA256 remains `9EADDD403FF6087873072572B610AC5F0B0DC96F9E944D5A6FE1523B2FE309A9`. All52 captured legacy domain-table hashes remain unchanged except the expected audit events from successful branding saves. Sessions/cache/migration bookkeeping excluded. No application or Postman database migration, push, merge or deployment.

Branch codex/m1-saved-branding; base908b6c5, uncommitted updates preserved. Files changed in repair: BrandingController.php, SavedBrandingTest.php, scripts/ui-preview.mjs and this evidence/developer record. Existing branding handoff source manifest refreshed to match current files. Saturnino UX/owner acceptance remain pending.

[Successful save screenshot](2026-10-27-M1-BERNIE-branding-save-repair/saved.png)
