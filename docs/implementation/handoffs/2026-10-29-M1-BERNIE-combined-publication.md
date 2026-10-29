# Combined platform administration publication

Date: 29 October 2026 (Asia/Manila). Contributor: Bernie.
Draft PR: https://github.com/scalyn-invited/Training-Assessment-Tracker-V2/pull/15
Branch: codex/platform-administration-publication, based on main 7d0928318cf5b27e7e2e00527a91bd7b1e385e12.

## Scope and reconciliation

The user explicitly authorised one PR containing all pending source changes, tests, assets, project documentation and curated verification evidence. A temporary Git index captured 97 files as snapshot 4504fa18259f584dec2cf3b740fdd9bd16818ebc without changing the original checkout or staging area. A separate managed worktree reconciled that snapshot onto current main.

Most M3 assessment work was already merged in PR #14 and therefore produces no duplicate change here. Four conflicts were formatting-only: assessment draft trailing whitespace, imported versus fully qualified test classes, and two migration evidence logs. Current main versions were retained. Bernie's independent client-selection and platform-theme entries were appended. Existing upstream CI/navigation fixes were preserved. The resulting PR includes the client/account selection repair, platform branding, pending logo asset and their tests/handoffs.

Excluded: browser-session snapshots/logs outside curated handoffs, duplicate platform/output evidence, a Word temporary lock file and ignored runtime data. A pattern check of the 40 initial PR files found no private keys, provider tokens or private account-link URLs. Synthetic test credentials remain confined to test fixtures. No real data, account changes, live services, merge or deployment.

## Validation

Source implementation commit: 6f1d128. Full PHP formatting, appearance JavaScript syntax and all 16 browser/server palette parity fixtures passed locally. Fresh isolated local qualification completed: 220 tests / 4,377 assertions, four skipped; five demo browser tests passed, including the new platform-theme test. Hosted run 40816607049 passed all four jobs against 6f1d128944523192890e9ec579ea6e5fb94fe58b: PHP/SQLite 218 passed and two skipped (4,383 assertions); MariaDB 255 tests / 4,616 assertions with two skipped; browser suite 19 navigation/UI plus five demo tests passed; document tools 18 tests / 100 assertions passed. Hosted assigned-assessment verification also passed within the browser job. The final documentation-only commit is rechecked by CI; the PR description/checks identify that final head and run.

Added repeatable platform palette coverage to the existing demo browser CI suite: unsaved previews, save/reload, orange/blue rendered colours, primary buttons and navigation, Platform Light/Dark, standard themes, reset, legacy browser preference migration, unchanged client branding, guest inheritance, desktop/mobile screenshots and axe checks. The test restores the previous platform configuration in finally. Earlier focused/manual qualification remains in the two included handoffs and is not a substitute for fresh PR validation.

Local setup required a temporary non-optimized Composer autoload configuration after optimized generation stalled. The temporary manifest/lock were removed; committed composer.json and composer.lock remain unchanged. Git publication used the existing GitHub CLI credential connection after the default credential manager stalled. Hosted CI uses the normal locked installation.

## Preservation and technical self-review

All 97 original pending files were rehashed after publication preparation and matched their pre-publication hashes. The original active application and saved branding were not modified. Only the isolated publication checkout contains this reconciliation and added CI coverage.

Bernie technical self-review (agent-assisted, not independent approval) covers the combined diff at 6f1d128: membership/permission checks preserved, password input excluded from retained form data, platform/client token and preference separation, optimistic save/audit preservation, upstream reconciliation and synthetic-only test paths. This publication note and whitespace cleanup do not change application behaviour. Saturnino UX/accessibility review and project-owner acceptance remain unclaimed. PR remains draft pending human review; no merge/deployment authorisation is implied.
