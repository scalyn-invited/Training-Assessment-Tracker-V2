# M1-BERNIE — combined PR, Scalyn integration and CI

The user explicitly authorised publishing the combined M1 implementation, opening a draft PR and running GitHub CI. They then selected: keep Scalyn branding/themes and integrate the sidebar.

Combined implementation commit: `c79ca5baec40feeb0bca8a2955bfa8627f700d3f`, based on `418dd7f`. Latest main `9d794e0` contains the previously merged Scalyn presentation. A local integration merge preserves that work; this does not merge the proposed PR into main.

Resolved the six overlapping areas: provider registrations, browser configuration, shared stylesheet, dashboard, shared layout and routes. Retained branding assets, all five themes, Appearance and existing navigation. Added active-client selection and scoped menu/preview gates. Branding and theme preferences now bind to server-validated client context, with no authority from legacy organisation/role fields. Mobile focus trapping, dismissal, inert background and resize cleanup remain in the dedicated navigation script; removed the duplicate navigation controller from appearance.js while preserving theme behavior.

The user-selected Scalyn presentation supersedes the green-only screenshots and source manifest in the earlier UI handoff. Earlier validation remains historical; the integrated revision requires fresh evidence.

Local integration PHP: 59 tests / 353 assertions passed. The expanded 16 browser scenarios are running; hosted CI is pending publication. CI now includes mapping safeguard tests, JS syntax checks and the dedicated isolated UI fixture, with browser artifacts uploaded. Mapping Python tests: four passed. Initial optional YAML parse was unavailable because the local Python runtime has no PyYAML; no installation was performed.

The initial Git push waited in the credential manager and was cancelled before a remote branch appeared. Publication uses the existing authenticated gh credential helper. No secrets, databases, Word lock files, CLI browser artifacts or unrelated untracked logo were selected for commit. No existing application/Postman DB migration, merge of the PR, deployment, live identity or paid service is authorised.

Saturnino's separate UX/accessibility review and full M1/project-owner acceptance remain pending. Final PR link, commit and CI outcomes follow below when verified.

## Publication and first CI run

Draft PR: https://github.com/scalyn-invited/Training-Assessment-Tracker-V2/pull/10. Integrated head: `3110fb102e7b3c15c258a9f5fa148d559d1538ea`; GitHub reported mergeable. CI run `40431145801`: MariaDB/concurrent workers and document utilities passed; PHP/SQLite stopped at Pint class spacing in AppServiceProvider; browser was still running when preparing the correction. This is not an all-green run.

Provider formatting was corrected and verified locally. Navigation now also closes when following a sidebar link (including same-page anchors). The expanded UI suite has a two-minute per-scenario outer budget; individual assertion deadlines remain unchanged. Local integrated Chrome checks passed theme persistence and identity/storage isolation, then timed out in later scenarios and reported a closed browser session; the run was stopped and diagnostics retained under `.runtime/ui-sidebar/results/`. No complete local integrated browser pass is claimed. Hosted browser verification remains authoritative for this revision's CI result.

## Browser corrections

First hosted browser result on 3110fb1: 14 passed / 2 failed. One failure was an ambiguous legacy `.secondary` test selector after adding the client switcher; it now targets the workspace-default button by accessible name. The other was a real focus-loop bug: closed theme-menu buttons could have layout rectangles while remaining non-interactive. The trap now excludes descendants of closed details except their summary.

Targeted local rerun after these fixes: both mobile scenarios passed (42.2 seconds), including Appearance navigation, responsive controls, Escape, backdrop dismissal, focus wrapping and desktop resize. Command: `npx playwright test --config=playwright.ui.config.js --grep "mobile sidebar supports|mobile drawer contains"`.

Second hosted run 40431971518 on ee01e98 passed PHP/SQLite and document utilities while preparing this fix; other jobs were still running. The next PR head must complete its own CI before an all-green claim. [Current integrated desktop](2026-10-27-M1-BERNIE-pr-ci/desktop-workspace.png) and [mobile drawer](2026-10-27-M1-BERNIE-pr-ci/mobile-menu.png) supersede the earlier green presentation.
