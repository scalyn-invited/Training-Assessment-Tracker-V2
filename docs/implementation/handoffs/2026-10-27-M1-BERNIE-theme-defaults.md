# M1-BERNIE: Client defaults, account themes and softer borders

## Source and reviewed scope

User-approved local continuation on `codex/m1-saved-branding`; starting/current HEAD `908b6c5c5c91b34552fc4e41c284e9e25b047359`. Earlier uncommitted branding/UI work preserved. [Exact changed-source hashes](2026-10-27-M1-BERNIE-theme-defaults/source-sha256.json). No push, merge or deployment.

Client Admin uses the explicit Client default theme selector under Client branding and Save client branding. Personal choices remain separate. Five built-ins and generated Client Light/Dark are available; legacy Client maps to Client Light on conversion. Existing defaults remain configured until explicitly saved. Account choices now persist per user/client/environment across browsers and devices. Use client default saves a null override and a recorded decision. Server-rendered precedence is personal override, client default, configured default; login/client selection remain platform-branded.

The additive migration adds nullable `client_brandings.default_theme` and `client_theme_preferences` with unique user/client/environment key, nullable theme, version and timestamps. New authenticated CSRF-protected web POST `/settings/appearance/preference` accepts selected client, expected version and nullable theme. It rechecks current access under the organisation lock, validates available themes, audits the change and rejects stale versions with409. Branding retains existing lock/version/audit/logo workflow. No sandbox API or Postman changes. Personal preference requests have a separate throttle budget from branding saves.

Browser storage no longer overrides account decisions. If a previous browser choice exists without an account decision, the page uses the client default and asks whether to keep that old choice. Either answer records the account decision; other devices cannot silently import their old value. Failed saves retain the current theme and show visible error feedback. Session revocation retains the existing redirect-to-login behaviour.

Generated palettes add a deterministic decorative token without changing v1 source metadata or existing accessible foreground/control tokens. Light borders are pale and brand-tinted; dark borders are subdued and lighter than their surfaces. Panels, dividers and preview cards use decorative borders; inputs, buttons and theme choices retain stronger boundaries and focus. Built-in themes and legacy manual palettes retain their prior styling. Shared PHP/JS fixtures include the additive token.

## Actual checks and failures

- SQLite relevant suite: **33 tests / 3078 assertions passed** (`ThemePreferenceTest|AutomaticClientThemeTest|SavedBrandingTest|BrandingTest|ClientPermissionIntegrationTest`). Receipt sqlite.xml.
- MariaDB11.4.13 same suite plus simultaneous branding/preference saves: **35 tests / 3092 assertions passed**. Independent workers verify only one preference version/audit commits. Receipt mariadb.xml.
- Separate MariaDB additive migration preservation: **1 test / 3 assertions passed**. Existing branding and historical rows preserved, new default null, preference table empty. Receipt migration.xml.
- Additional palette/border check after adding relative softness assertions: **2 tests / 2793 assertions passed**. Text/control contrast remains verified across shared fixtures and125 RGB-cube sources. Node parity: **16 fixtures passed**.
- Initial browser suite: **6 passed / 1 failed**. Repeated personal selections consumed branding's shared throttle budget, producing429. Corrected with a dedicated preference throttle prefix. The affected generated-theme scenario then passed.
- First targeted rerun: generated-theme scenario passed; cross-device rerun failed because a prior run's account decision correctly suppressed the old-browser prompt. Updated isolated fixture setup to clear preference decisions and reset the default between scenarios; no product permission behaviour was relaxed. Final cross-device rerun receipt is recorded below.
- Initial PHP failure expected401 for revoked access; existing middleware returns302 to login. Corrected expectation; rerun passed. Pint initially flagged line endings on one newly edited test; fixed and final check passed. JavaScript syntax/parity and whitespace checks passed (Git line-ending warnings only).

Browser coverage includes five built-in themes, identity isolation, storage unavailable, mobile keyboard/navigation, logo/save, generated light/dark extreme colours, server/browser palette parity, old browser decisions, account preferences across separate browser contexts, reset, stale-edit rejection, axe WCAG A/AA checks and overflow. No claim of a final combined7/7 rerun: final outcomes are split across runs above. Screenshots inspected: [desktop](2026-10-27-M1-BERNIE-theme-defaults/desktop.png), [mobile](2026-10-27-M1-BERNIE-theme-defaults/mobile.png). Full repository suite, hosted CI and manual screen-reader review not run.

## Preview and data preservation

Preview: http://127.0.0.1:8124/settings/appearance, synthetic Alex/Client Admin. Refresh; choose Client default theme and Save client branding. Users may choose their own theme or Use client default. The existing preview remains running and login returnedHTTP200.

Backed up UI database through SQLite's backup API to `.runtime/preferences/ui-before.sqlite` before applying only `2026_10_27_120000_add_theme_preferences.php` with the guarded UI environment. Verified backup domain hashes. Original application47 and Postman53 domain-table hashes unchanged. UI54 prior domain tables preserved, except expected migration bookkeeping; existing branding including logo/palette preserved and new default null. New preference table empty. Sessions/cache excluded from domain comparison; no byte-identical claim for active databases. No migration/reset targeted application or Postman databases. Temporary MariaDB shut down after qualification. Browser fixture uses only `.runtime/preferences/browser.sqlite` on8128.

## Technical self-review and handoff

Bernie's self-review covered server precedence, client/environment/user scope, explicit one-time legacy decision, version conflicts, access recheck under shared organisation lock, audit transaction, independent throttles, validation and missing-storage behaviour, no silent conversion during reads, v1 palette compatibility, and distinct decorative/control boundaries. Screenshots show light panels with subtle borders and visible controls; mobile preview stacks without horizontal overflow.

Saturnino: separately review default-versus-personal wording, the one-time browser choice prompt, save-error placement, keyboard focus, soft borders in both variants and the long mobile form. Preserve server permission/version contracts. Saturnino's record was not edited. Independent UX/accessibility review, project-owner approval, hosted CI and full M1 acceptance remain pending. No paid services/live identity connection.

Final cross-device rerun: **1/1 passed (30.7s)** after isolated scenario reset. Final browser results therefore cover every scenario with passing evidence across the initial and targeted runs, not one combined rerun. [Member mobile](2026-10-27-M1-BERNIE-theme-defaults/member-mobile.png). No remaining local test failure; review/CI boundaries above remain.
