# M1-BERNIE: One brand colour and automatic light/dark themes

## Scope and source

User-approved replacement of the four manual controls with one Brand colours picker and matching hex field. Implemented locally on `codex/m1-saved-branding`, starting/current revision `908b6c5c5c91b34552fc4e41c284e9e25b047359`, preserving earlier uncommitted saved-branding work. This handoff supersedes the four-colour editor instructions in the earlier client-theme handoff. No push, merge or deployment. [Exact reviewed file hashes](2026-10-27-M1-BERNIE-automatic-theme/source-sha256.json).

## Behaviour and technical self-review

The editor initially uses the saved manual primary, generated source, or existing brand accent in that order as applicable. One colour generates side-by-side Client Light and Client Dark samples, stacking on mobile. The source remains intact; backgrounds, cards, text, muted text, links, controls, borders, focus and hover states derive deterministically. PHP and browser implementations use identical integer mixing and contrast calculations, pinned by 16 shared fixtures. Foreground adjustment terminates at a known contrasting endpoint on the deliberately bounded light/dark surfaces. Text/link/button targets are at least 4.5:1, main text 7:1 and boundaries 3:1. Black, white and pale colours are accepted without asking the editor to repair combinations.

The existing `theme_colours` JSON stores `{generation: 1, source: '#rrggbb'}`. No new migration, schema or public API. Existing manual palettes render as before until an explicit successful save. The save converts the palette and makes Client Light/Dark available; it does not select a theme for other people. A stored `client` preference resolves to Client Light after conversion; built-in preferences remain unchanged. Five built-in theme definitions are preserved.

The existing authenticated save action retains selected-client validation, transaction/organisation lock, fresh permission check, optimistic version, audit, private logo handling and failure cleanup. Obsolete four-colour submissions receive a reload message and cannot alter the saved palette. Unknown generation versions and malformed stored colour data do not become CSS. The source field is validated server-side even if browser validation is bypassed. Personal preferences remain scoped per user/client/environment/browser.

Reviewed files cover the palette service/browser helper, branding resolver/controller, layout/Appearance templates, stylesheet/theme script, shared fixtures, regression/browser tests, concurrency worker, isolated browser configuration and CI parity command. Earlier modified/untracked files remain preserved. This is Bernie's technical self-review, not independent technical approval or Saturnino UX acceptance.

## Actual verification

- SQLite: `php -d extension=gd vendor/bin/phpunit --filter 'AutomaticClientThemeTest|SavedBrandingTest|BrandingTest|ClientPermissionIntegrationTest' --log-junit .runtime/automatic-theme/sqlite.xml`: **30 tests, 3043 assertions passed**.
- MariaDB 11.4.13: same filter plus `test_competing_branding_saves_commit_one_version_and_audit`, using only `.runtime/m1-qualification-20261026/phpunit.xml`: **31 tests, 3050 assertions passed**. Concurrent editors produce one committed version/audit; the other receives a conflict. Temporary server shut down with the verified task-owned shutdown helper; port13318 no longer listening.
- `node tests/Support/check-client-palettes.mjs`: **16 parity fixtures passed**. PHP additionally checks 125 RGB-cube source colours and both variants. Fixtures include saturated, pale, neutral, dark, black and white.
- `npx playwright test --config=playwright.automatic-theme.config.js`: **6/6 passed**, no failures, skips or flaky tests, 117.8 seconds. Fresh synthetic `.runtime/automatic-theme/browser.sqlite`, port8127. Covers all five built-ins, identity/preference isolation, keyboard/mobile navigation, branding save/logo, unsaved preview, picker/hex synchronization, matching generated preview and workspace tokens, reload, legacy preference resolution, dashboard, overflow and axe WCAG A/AA checks across generated variants and extreme colours. Test server stopped; port8127 no longer listening.
- `php vendor/bin/pint --dirty --test`, JavaScript syntax checks and `git diff --check` passed. Git emitted line-ending warnings only. Desktop/mobile screenshots inspected: single control pair, two desktop previews, stacked mobile previews, no visible horizontal overflow.
- Failures/skips for this verification: none. Full repository suite, hosted CI, exhaustive testing of all 16.7 million input colours, manual screen-reader review and Saturnino review were not performed. Automated accessibility results are scoped to tested screens/states.

## Data preservation and preview

No database migration or data-writing command targeted the existing application, Postman database or existing previews. Read-only before/after domain-table hashes match for the original application (**47 tables**), existing UI preview (**54 tables**) and branding preview (**54 tables**), including saved branding. Runtime sessions/cache/cache_locks are excluded. Comparison uses stable row ordering; the initial after-check used rowid order and showed ordering-only differences, resolved by matching the original stable serialization. Evidence includes before/after hashes and per-table comparison receipts. No byte-identical assertion is made for databases with running session/cache activity. Postman was not independently hash-verified in this slice and was not targeted.

Existing preview remains **http://127.0.0.1:8124/settings/appearance** (synthetic Alex/Client Admin sign-in). Port8124 was verified listening and login returned HTTP200. Current saved branding is preserved; refresh, choose a colour, explicitly Save client branding, then select Client Light or Client Dark. Current services on other ports were left alone. No paid/live identity service used.

## Saturnino handoff and remaining gates

Review Appearance on desktop/mobile: single picker plus editable hex, generated light/dark sample cards, seven-option gallery after save, validation/save feedback, keyboard focus and long mobile layout. Preserve scoped save/version/audit and legacy conversion contracts. Review readability and hue fidelity of extreme colours; colour adjustments deliberately prioritise contrast. Screenshots: [desktop](2026-10-27-M1-BERNIE-automatic-theme/desktop.png), [mobile](2026-10-27-M1-BERNIE-automatic-theme/mobile.png). Saturnino's record was not edited. Separate UX/accessibility review, project-owner approval, hosted CI and full M1 acceptance remain pending.
