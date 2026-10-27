# M1-BERNIE: Selectable client theme and individual colours

## Confirmed scope and delivery

The user chose individually configurable primary, background, card and text colours, as a separate selectable Client theme alongside the existing five themes. The existing Brand accent label is now Brand colours. Implementation continues locally on `codex/m1-saved-branding`, base `908b6c5c5c91b34552fc4e41c284e9e25b047359`, preserving all previously uncommitted work. Current file inventory/hashes: [source manifest](2026-10-27-M1-BERNIE-client-theme/source-sha256.json). No commit, push, merge or deployment performed.

The existing decorative brand colour remains separate from the four Client theme controls. Initial editor values reuse the existing Light theme. Saving a valid palette makes Client theme selectable for authorised users of that client. Neither saving nor migration changes anyone's personal selected theme or the five existing palettes. Choose Client theme in the Appearance gallery or Theme menu after saving. Personal selection remains per user/client/environment/browser and survives navigation and reload. No custom palette on shared login/client-selection screens.

## Implementation and self-review

Additive nullable `theme_colours` JSON on existing client/environment branding rows. Four optional form fields on the existing authenticated save action: theme_primary, theme_background, theme_card, theme_text. When any is supplied, all four must be valid six-digit hex colours. Legacy submissions without these fields preserve the saved palette. Existing selected-client authorisation, organisation locking, permission recheck, optimistic version and audit transaction apply unchanged. Audit captures palette changes. Stored palettes are revalidated before rendering; malformed/inaccessible palettes fail closed to the existing themes, never raw CSS.

Text and primary colour must each contrast at least 4.5:1 with both background and card colours. Primary is also used for link/control text. Black or white button text is selected automatically for contrast; focus and border colours derive from the validated text colour. Validation returns specific colour pairs without saving inaccessible settings. Browser preview and live contrast guidance mirror server calculations. A failed validation preserves previously saved settings. Theme tokens are emitted only for the authenticated client's validated palette; the optional Client theme applies tokens only when selected. Hardcoded hero colours are overridden within Client theme only. Updated asset versions prevent stale scripts/styles after the feature change.

Bernie's technical self-review covered typed colour validation, CSS injection prevention, saved-theme scope, guest/client switching, preservation of personal preferences, concurrent saves and preview contrast. Separate Saturnino UX/accessibility review and owner/full M1 acceptance remain pending.

## Actual verification

- SQLite: `php -d extension=gd vendor/bin/phpunit --filter 'SavedBrandingTest|BrandingTest|ClientPermissionIntegrationTest' --log-junit .runtime/client-theme/sqlite.xml`: **29 tests / 271 assertions passed**.
- Disposable MariaDB11.4.13, same filter plus concurrent branding save and theme migration preservation: **31 tests / 280 assertions passed**. The concurrent save worker now supplies a complete palette. Only one contender commits one version/audit. The migration test preserves an existing branding row including name, logo reference and version. Temporary MariaDB was shut down after testing.
- `npx playwright test --config=playwright.client-theme.config.js`: final **6/6 passed (2.0m)**. All five original themes, storage and identity isolation, mobile keyboard navigation, save/upload, unsaved preview, custom light/dark palettes, contrast rejection, reload persistence, gallery selection, dashboard styling, desktop/mobile automated accessibility and overflow are covered.
- First browser run: **4 passed / 2 failed** due to preview paragraphs inheriting the selected built-in theme's muted text rather than the chosen preview text. Corrected the preview CSS and reran the full suite successfully. The new custom-theme scenario passed in both runs. Prior failure receipt retained locally at `.runtime/client-theme/browser-first.json`.
- Pint, JS syntax and whitespace checks passed. Screenshots inspected for desktop/mobile layout. Automated accessibility checks do not establish Saturnino acceptance. Hosted CI and the full repository suite were not run for this uncommitted slice.

## Data and preview

Both known synthetic previews were backed up before applying only `2026_10_27_110000_add_client_theme_colours.php`: `.runtime/client-theme/ui-before.sqlite` and `branding-before.sqlite`. All **53** recorded domain tables in each preview retained their previous values; the new palette column is initially null. Sessions/cache/migration bookkeeping excluded. The separate browser tests use a fresh `.runtime/client-theme/browser.sqlite` on8126, never the user's open8124 session. Browser test server stopped afterward.

Original application hash was `9EADDD403FF6087873072572B610AC5F0B0DC96F9E944D5A6FE1523B2FE309A9` at the migration-integrity check. Final check found `72C0F54300A25453AD70101DAEC77581A04D3B45B13490B62F1C559A6AB91464`, with file modification at 10:36:51 Asia/Manila. Read-only investigation confirmed no new table or migration (latest remains 2026_10_05 document extractions), no branding table in the original database, and all domain tables match the preserved pre-backfill source snapshot; only sessions/cache differ. An independent application server remains running on port8000. Runtime activity is consistent with this difference, but the exact writer was not instrumented. No byte-identical claim is made; no application or Postman migration/reset performed. User's existing stored branding and selected theme on8124 were preserved; no arbitrary demonstration palette was published there.

Use http://127.0.0.1:8124/settings/appearance (Alex, synthetic Client Admin). Refresh the page, edit the four Client theme colours, Save client branding, then choose Client theme. Port8125 also has the additive migration; both original preview processes are preserved. UI preview startup verifies the new column is ready. No public API or Postman changes.

[Desktop](2026-10-27-M1-BERNIE-client-theme/desktop.png) · [Mobile](2026-10-27-M1-BERNIE-client-theme/mobile.png)

Saturnino handoff: review the distinction between the decorative Brand colours field and Client theme controls, colour-picker usability, contrast-error guidance, long mobile form, theme-gallery expansion and high-contrast derived borders. Keep the scoped save contract and server-side contrast gate intact during refinement.
