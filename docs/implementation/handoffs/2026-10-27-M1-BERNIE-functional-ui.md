# M1-BERNIE — functional UI and sidebar navigation

Date: 2026-10-27. Owner: Bernie. Status: ready_for_review (local UI slice only).

## Source and authorisation

Base HEAD: `418dd7f5c2237d1c11d73bced4e6b11a26a4eca7`. Local branch: `codex/m1-functional-sidebar`. The pre-existing dirty M1 permission/backfill implementation is preserved. This is an uncommitted working-tree delivery, not a claim that the base commit contains the UI.

The user approved all existing screens, the existing green/neutral style, and Bernie's ownership of shared layout/navigation/styles during this pass, with Saturnino following afterward. No counterpart review is inferred. No backend contract, route, permission policy or schema was changed in this slice.

## Implementation and review contract

- Shared Blade shell: 248px desktop sidebar; Workspace, People, Review queue, Updates, Administration and AI settings use the existing Access checks. Current section is marked with `aria-current`. Training detail routes remain under Workspace, with existing enrolment-specific links retained.
- Header: active-client selection, current account and sign-out remain visible. Client changes continue through the existing CSRF-protected action.
- At widths up to 900px: labelled Menu button opens a modal drawer, traps keyboard focus, makes the underlying app inert, closes on Escape/Close/backdrop, and restores focus. Desktop resizing clears the modal state. Without JavaScript the menu remains available in normal document flow.
- Shared presentation: green/neutral tokens, 44px action controls, labelled forms, panel spacing, visible focus, responsive grids, wrapped long content, table scrolling and existing success/error/loading states. No fabricated metrics, disabled placeholder workflows, new dependencies or build pipeline.
- Dashboard empty-state copy now describes current client access instead of claiming that team membership alone determines visibility.
- Existing browser regression expected broad admin training visibility; corrected to zero plans under the approved scoped model. The browser fixture helper refreshes only synthetic imported permission timestamps at each scenario boundary; actual expiry and authorization logic are unchanged.

## Screen coverage

All views inherit the shared shell/styles: sign-in; client selection; dashboard; people; administration; enrolment detail and summary-only detail; onboarding; curriculum; learning overview; lesson; submission receipt/review; review queue; progress; updates; AI settings; generation status; document extraction/review; Livewire background receipts.

The sidebar owns top-level navigation. Existing step/block links remain contextual. Forms retain their existing fields, validation, submit actions and data-preservation behavior. Sensitive access, self-approval denial, quarantine, answer-key visibility and stale-session handling remain enforced server-side.

## Verification

Final results: **12/12 browser tests passed** (4.6 minutes), including automated WCAG-tagged checks, role-specific menu visibility/active state, mobile focus trapping/Escape/backdrop/resize, all top-level desktop/mobile screens, document review, autosave/recovery, private downloads, queued receipts, curriculum approval, generation, lesson submission, human grading and progress. **79 PHP tests / 444 assertions passed**, covering client switching, foreign-context denial, summary restrictions, relationship-driven revocation and stale sessions/tokens alongside learning/delivery regressions. Pint, JavaScript syntax and final whitespace checks passed. No skips in these selected runs.

First browser run: 6 passed / 6 failed. Failures were a legacy broad-admin expectation (1), expired synthetic imported permission snapshots (3), and accessibility checks before stylesheet load (2). The admin expectation and synthetic scenario freshness were corrected; sidebar checks now wait for load/styling, and the skip link has an explicit 44px minimum target. Full corrected run passed; first-run results are retained rather than hidden.

[Machine-readable results](2026-10-27-M1-BERNIE-functional-ui/verification-results.json), [exact file hashes](2026-10-27-M1-BERNIE-functional-ui/source-manifest.json), [desktop workspace](2026-10-27-M1-BERNIE-functional-ui/desktop-workspace.png), [mobile menu](2026-10-27-M1-BERNIE-functional-ui/mobile-menu.png) and [mobile administration](2026-10-27-M1-BERNIE-functional-ui/administration-mobile.png). Additional screenshots, first/final browser reports and PHPUnit XML are alongside these files.

Technical self-review by Bernie's implementation session: checked existing server Access calls remain the source for menu visibility, form routes and CSRF are preserved, no hidden-content policy was broadened, mobile modal state is removed on close/resize, no-JavaScript fallback leaves navigation available, and preparation targets only the dedicated UI fixture. Inspected desktop workspace, mobile enrolment, mobile administration and drawer screenshots. This is technical self-review, not Saturnino's UX acceptance. Original application DB SHA256 at start: `9EADDD403FF6087873072572B610AC5F0B0DC96F9E944D5A6FE1523B2FE309A9`.

Commands from `platform/`:

```powershell
node scripts/ui-prepare.mjs
npx playwright test --config=playwright.ui.config.js
php vendor/bin/phpunit --filter "ClientPermissionIntegrationTest|FoundationTest|LearningTest|DeliveryTest|NotificationAndScanTest" --log-junit .runtime/ui-sidebar/phpunit.xml
php vendor/bin/pint --test
node --check public/navigation.js
node --check tests/Browser/sidebar.spec.js
node --check tests/Browser/isolated-cache.js
node --check scripts/ui-preview.mjs
```

UI rehearsal uses only `.runtime/ui-sidebar/ui.sqlite` on port 8124, local mock sign-in and array mail. Preparation refuses to overwrite an existing UI fixture. The first-run database and failure traces are retained under `.runtime/ui-sidebar/first-run.sqlite` and `first-results/`; after preserving that database a new UI fixture was prepared for the corrected full run. The original application database and the port-8123 Postman database were not reset, seeded or migrated.

The Postman database is still served by its existing process. Its byte hash changed during this pass, with live session/cache activity and requests in the existing server log; this is not evidence of a migration or backfill. Do not claim a byte-identical Postman database or overwrite it to restore an earlier hash.

## Preview and Saturnino handoff

Start from `platform/` with `node scripts/ui-preview.mjs`, then open `http://127.0.0.1:8124/login`. Use Alex for administration, Casey or Morgan for coordinator UI, and Jordan for member UI. Sam is an imported synthetic identity subject to the unchanged five-minute directory freshness limit; the automated suite refreshes that fixture between scenarios, but the preview does not bypass expiry.

Refinement entry points: `resources/views/layouts/app.blade.php`, the Shared functional UI section of `public/app.css`, and `public/navigation.js`. Preserve route names, permission gates, active-client context, drawer focus behavior, form hooks and contextual training navigation. Review density/readability of long administration forms, navigation grouping, small-screen controls, explanatory language and screen-reader behavior. Screenshots are implementation evidence, not Saturnino's acceptance.

Pending gates: Saturnino UX/accessibility review; hosted CI/PR; project-owner and full M1 acceptance. No push, merge, deployment, paid service or live identity connection. MariaDB was not started for presentation-only changes. No formal assistive-technology certification or cross-browser acceptance is claimed.

The temporary browser-test server stopped after the suite. A separate local UI preview was then started on 8124 and intentionally left running for review; PID is in `platform/.runtime/ui-sidebar/preview.pid`. Port 8123 remains the independent Postman preview. No MariaDB or queue worker was left running by this slice.
