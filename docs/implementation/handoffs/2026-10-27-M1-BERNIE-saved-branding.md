# M1-BERNIE: Saved client branding and private logo upload

## Delivery and source

User approved immediate Client Admin saves for all displayed fields and one logo shared across themes. Implemented locally on `codex/m1-saved-branding`, starting HEAD `908b6c5c5c91b34552fc4e41c284e9e25b047359`. Changes remain uncommitted; exact delivered file hashes and changed-file inventory are in [source-sha256.json](2026-10-27-M1-BERNIE-saved-branding/source-sha256.json). Existing documentation receipts, lock file, unrelated logo and browser artifacts were preserved. No push, merge or deployment performed for this slice.

## Contract and technical self-review

Appearance retains personal browser theme choices and adds a client-wide save, discard, upload/replace/remove and save/error feedback. Saved client display name does not alter directory ownership/name. Client/environment persisted overrides take precedence over configuration. Platform sign-in and client-selection retain platform branding. Unsaved accent is previewed; saved accent decorates borders/active indicators while tested theme tokens control text and controls, preserving contrast for arbitrary accents.

`POST /settings/appearance` accepts existing preview field names, selected `client_id`, optimistic `version`, optional `logo`, and `remove_logo`. Authenticated session/CSRF and scoped administration checks apply. The submitted client must match validated context. Organisation locking serializes saves with client permission changes, permission is rechecked inside the transaction, and stale versions return a reload message. Branding changes do not change permissions or invalidate sessions. Audit records contain actor/client/version and changed values. Global Admin uses existing directory authority, without receiving sensitive-content authority.

`GET /branding/{client}/logo` requires current selected-client access, uses private no-store PNG responses and never accepts a storage path. Files are validated as PNG/JPEG/WebP, limited to 2 MB / 4096 pixels per side, decoded and re-encoded to PNG. No SVG/remote URL support. New files are staged before the transaction and deleted on failure; old files are removed only after commit. Cleanup errors are reported without falsely rolling back successful publication. Removal restores configured defaults. GD is required for uploads; unavailable processing returns a field error. CI jobs now enable GD. The preview enables GD per process, leaving the machine PHP configuration unchanged.

Additive `client_brandings` table is keyed by organisation/environment, with version and logo reference. Existing un-migrated databases continue to render configured branding; this does not make saves available without the separately authorised migration. No sandbox API changes or Postman collection.

Bernie's technical self-review: reviewed client context, transactions/row lock, stale revisions, file lifecycle, escaped labels, role boundaries, guest rendering and existing data preservation. No legacy authorization fallback, sensitive access expansion or executable uploaded content introduced. Saturnino's independent UX/accessibility review and owner/full M1 acceptance are pending.

## Executed verification

Commands run from `platform/`:

- `php -d extension=gd vendor/bin/phpunit --filter 'SavedBrandingTest|BrandingTest|ClientPermissionIntegrationTest'`: **23 tests / 208 assertions passed** on SQLite.
- Same filter with `-c .runtime/m1-qualification-20261026/phpunit.xml`: **23 tests / 208 assertions passed** on disposable MariaDB **11.4.13**, port 13318, guarded qualification data directory. Credentials remain local/ignored.
- MariaDB `--filter ClientConcurrencyTest`: **8 tests / 50 assertions passed**, including two concurrent branding saves (one success, one stale-version rejection, one audit event) and existing access-change races.
- MariaDB `--filter ClientMigrationMariaDbTest`: **1 test / 14 assertions passed**, including branding additive migration against approved learning/submission history. Only an empty disposable branding table was removed to establish the pre-migration state; no populated foundation rollback was used by the added branding check.
- Disposable SQLite copy of the existing synthetic UI snapshot: migration applied and hashes of **all 55 existing tables unchanged**, excluding migration bookkeeping. Evidence included.
- Browser `npx playwright test --config=playwright.branding.config.js`: initial result **4 passed / 1 failed**. Failure was an image-load assertion before image completion, corrected to wait. Final targeted `--grep 'client branding saves'`: **1 passed (25.7s)**, including save/upload persistence, all five theme accessibility scans, mobile overflow, discard and personal-theme persistence. Do not describe this as a fresh combined five-test pass. Original four covered theme persistence, identity separation/storage failure, keyboard/mobile drawer and unsaved preview.
- A direct-service branding regression initially failed when guest-screen handling was placed in the resolver; moved guest-screen selection to the view composer and reran successfully. Visual inspection also caught the stale preview-only helper text, now corrected in final screenshots.
- `php vendor/bin/pint --test --dirty`, JS syntax and `git diff --check`: passed. No hosted CI run for this uncommitted slice; full repository suite not rerun. Existing runtime browser cache helper initially targeted the older UI fixture; corrected to explicitly guard the separate branding fixture before final rechecks. No application/Postman migration or reset occurred.

Original application database SHA-256 before/after: `9EADDD403FF6087873072572B610AC5F0B0DC96F9E944D5A6FE1523B2FE309A9`. No Postman database mutation was requested or performed. Temporary MariaDB shut down through its identity-checked shutdown script after final tests.

## Preview and Saturnino handoff

Preview: http://127.0.0.1:8125/login. Choose **Alex — Administrator**, then **Appearance**; Jordan is the member account for read-only checks. Synthetic Northstar branding and the repository's Scalyn image demonstrate persistence/upload. No password or live identity required. Dedicated database: `.runtime/branding/preview.sqlite`.

Start again with `node scripts/branding-preview.mjs`; first-time empty fixture preparation is `node scripts/branding-preview.mjs --prepare`, which refuses to overwrite an existing fixture. Current background process ID is stored in `.runtime/branding/preview.pid`. Port 8124 and the Postman instance are preserved.

[Desktop screenshot](2026-10-27-M1-BERNIE-saved-branding/desktop.png) · [Mobile screenshot](2026-10-27-M1-BERNIE-saved-branding/mobile.png)

Saturnino review requested in this handoff: Appearance form hierarchy, selected-client explanation, upload/removal/discard feedback, validation/reload states and logo presentation across themes/mobile. Reuse the shared panel, detail-grid, form-actions and theme tokens; do not alter authorization or image-processing contracts without Bernie coordination. This handoff does not claim that Saturnino has reviewed or accepted the work. Production cutover, hosted CI and full M1 acceptance remain separate.
