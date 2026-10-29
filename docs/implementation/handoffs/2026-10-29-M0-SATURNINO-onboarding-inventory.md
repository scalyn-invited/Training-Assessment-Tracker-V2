# M0 Saturnino onboarding and UI inventory handoff (DRAFT)

**Status: draft, in progress.** Prepared with AI assistance (Claude Code, at Saturnino's request). Saturnino has not yet reviewed this draft; nothing here is a recorded UX review, technical self-review or project-owner acceptance. Section 4 findings come from reading code; section 4a records what Saturnino observed himself in the running local app (Casey, coordinator) on 2026-10-29.

- Date/time and timezone: 2026-10-29, Asia/Manila (drafted ahead of the 30 October 3:00 PM first-week review).
- Author / contributor / applicable review owner: Saturnino (Developer B, UI and Design), AI-assisted. UX/accessibility review owner: Saturnino, pending. Technical self-review: Bernie, not applicable to this lane's documentation.
- Task ID and bounded slice: `M0-SATURNINO`, scope reconciliation and onboarding, UI-relevant lane. No feature code, schema, service, policy or contract change.
- Branch/worktree and exact commit: branch `m0-saturnino-onboarding`, created from local `main` at `9dc6406`. Dirty state: modified `docs/implementation/developers/SATURNINO.md` and this new file. `origin/main` has since advanced to `7d09283` and `origin/codex/m2-local-production` exists; neither was merged locally. Nothing pushed, no PR.
- **Baseline caveat:** every observation, test result and route/screen count below was taken on `9dc6406`. When this handoff was prepared for review, `origin/main` was at `af05855` and already included M2 (password accounts, invitations, changed sign-in) and M3 (versioned assigned assessments with human review) from Bernie. The "Gap" rows for assessment assignment in section 3 and the sign-in description are therefore **out of date against current `main`** and have not been re-verified. Bernie's M2 handoff also contains a pending UX review request (`handoffs/2026-10-27-M2-BERNIE-local-password/UX-REVIEW-REQUEST.md`), which this handoff does not answer. The Development Planning findings in section 4a were not re-checked against the newer `main`.
- Requirement IDs and PDF sections: V2-01 through V2-15, PDF sections 1-37 (coverage owned by Bernie: `2026-10-26-M0-BERNIE/coverage.md`; not re-derived here).
- Accepted decisions and contract versions consumed: none. D01-D10 open, C01-C07 `proposed_v0`.
- Changes and affected files: `developers/SATURNINO.md` (claim section) and this handoff only. Git-ignored local artefacts created by setup: `platform/.env`, `platform/database/database.sqlite`, `platform/vendor/`.
- New/changed contracts: none. Migrations/backfill/rollback: none (setup ran the existing migrations on a fresh local SQLite file).

## 1. Reproducible setup record

Environment: Windows 10, PHP 8.2.33, Composer 2.10.2, Node 22.16.0, Git. Database: new local SQLite file, synthetic data only, no existing or shared database touched.

| Step | Command (from `platform/`) | Result |
| --- | --- | --- |
| Dependencies | `composer install --no-interaction` | Succeeded |
| App setup | `php scripts/setup.php` | Succeeded: migrations ran, `ClientAuthoritySeeder` seeded, "Synthetic workspace ready" |
| PHP extension fix | Uncommented `extension=gd` in the user-level `php.ini` (backup saved as `php.ini.bak-before-gd` beside it) | `php -m` lists `gd`; this is a machine change outside the repo |

Not done: `npm.cmd ci` (only needed for browser tests), queue workers, `php artisan serve`.

## 2. Baseline checks actually run

All local, SQLite in-memory (`phpunit.xml` forces `DB_DATABASE=:memory:`), branch `m0-saturnino-onboarding`, tree dirty only as above.

| Check | Result | Notes |
| --- | --- | --- |
| `php artisan test --compact` (first run, before GD) | 170 passed, 2 skipped, 7 failed, 862 s | All 7 failures: "GD extension is not installed" (`SavedBrandingTest` x5, `PlatformAdministrationTest` x2) |
| `php artisan test --compact tests/Feature/SavedBrandingTest.php tests/Feature/PlatformAdministrationTest.php` (after GD) | 23 passed, 262 assertions, 181 s | The 7 failures were environmental |
| `php artisan test --compact` (full rerun after GD) | 177 passed, 2 skipped, 0 failed, 3979 assertions, 401 s | Exit code 0 |
| `php vendor/bin/pint --test` | passed | Code style only |

Skipped tests: two. The source contains skip conditions for ClamAV not installed (`DocumentScannerTest`), PHP ZipArchive unavailable (`DocumentParserTest`) and Poppler/Tesseract not configured (`DocumentParserTest`). Which two fired was not confirmed; the zip extension is loaded here, so the other two are the likelier ones.

Blocked / not run:
- `php vendor/bin/phpunit -c phpunit.mariadb.xml`: **blocked**. MariaDB and `pdo_mysql` are not installed. The config expects 127.0.0.1:3308, user `root`, empty password, database `training_foundation_test`.
- `npm.cmd run test:browser`: **not run** (needs `npm.cmd ci` and Google Chrome; not yet checked).
- `php scripts/restore-drill.php`, `node scripts/load-rehearsal.mjs`: not run.

Historical evidence (not reused as new): Bernie's M0 verification and the hosted CI run on PR #9 are his records; this table is a fresh local run on a different machine.

## 3. Route and screen inventory (from `php artisan route:list`, 57 application routes; views read, not rendered)

| Area | Routes / screens | Status for the product plan |
| --- | --- | --- |
| Sign-in | `/login` (synthetic identity picker), logout | Exists; live SSO/MFA not connected |
| Workspace | `/dashboard` (member-style welcome, plan cards), `/people`, `/clients`, `/platform/*` | Exists; no coordinator attention queue, pending-review counts or per-role dashboard |
| Branding | `/settings/appearance`, `/platform/appearance`, `/branding/{client}/logo` | Exists (M1 work by Bernie) |
| Enrolment | `/enrolments/{id}` (details, private evidence upload, background check) | Exists |
| Onboarding / planning | `/enrolments/{id}/onboarding/{step}` (7 steps), `/enrolments/{id}/curriculum` (editor, versions, AI draft, approval) | Exists; plan length is "4/6/8/10/12 blocks", no explicit 30-day mode |
| Learning delivery | `/enrolments/{id}/learn`, `/lessons/{id}/{index}` (+ save, submit, quiz) | Exists |
| Progress | `/enrolments/{id}/progress` | Route exists; content not reviewed here, so weekly-report coverage is unconfirmed |
| Review | `/reviews`, `/submissions/{id}`, `/files/{id}/extraction` | Exists (lesson submissions; not standalone assessment management) |
| Notifications | `/notifications` | Exists |
| AI | `/settings/ai`, `/generation/{id}` | Exists |
| Assessment assignment | none found | **Gap** |
| Member notes to coordinator | none found | **Gap** |
| Growth graph / weekly report | none found by route name | **Gap (unconfirmed for `/progress`)** |
| Chatbot | none found | **Gap** |

## 4. Development Planning UI/UX findings (code reading only; confirm visually)

1. Competency and KPI links are bare number inputs ("Target competency number", "Linked KPI number"); a named selector would be clearer.
2. Plan length is offered as "blocks"; there is no 30-day mode (plan gap for M4, not a defect).
3. The curriculum editor is one long form per block: six text areas plus five number fields per day, only the first day open; no summary view of the whole plan.
4. Internal wording leaks to members and coordinators ("Saved draft ≠ approved plan", "Baseline approval does not activate training", "Future adaptation cutoff", "Calendar version").
5. Lesson headings show raw `YYYY-MM-DD` dates.
6. Dashboard has no role-specific signals (coordinator queue, awaiting review, falling behind).
7. Accessibility positives visible in the markup: `aria-current` on step navigation, `sr-only` legends on fieldsets, explicit labels, polite live region for autosave. Contrast, keyboard order, focus handling, responsive layout and chart alternatives are **not assessed**.
8. For Bernie, outside this lane: `learning/plan.blade.php` and `learning/onboarding.blade.php` run Eloquent queries inside the view (`AiRun::where`, `User::find`). Noted, not touched.

## 4a. Observed in the running app (Saturnino, local app, signed in as the synthetic coordinator Casey, 2026-10-29)

Journey walked: onboarding steps 1-7 for the synthetic member "Sam", confirm assessment findings, confirm shared calendar, create curriculum draft, edit Day 1, save version 2, validate all blocks. Screenshots are held locally by Saturnino (not committed): `screencapture-127-0-0-1-8000-enrolments-...-onboarding-2026-10-29-12_13_09.png`, `...-curriculum-2026-10-29-13_17_19.png`, `...-13_26_49.png`, `...-13_35_04.png`.

| # | Observation | Evidence |
| --- | --- | --- |
| 1 | "Create curriculum draft" fails with "A coordinator must confirm the assessment findings before creating a plan." The message is small grey text at the bottom of a long page; the review step does not link back to the assessment step, and the button is not disabled. It works once "Confirm assessment findings" is clicked on step 3. | onboarding screenshot 12:13 |
| 2 | Review step lists each section as "saved, review completeness below", but no completeness list follows. "Unsaved changes." shows under the calendar form directly after "Shared capacity calendar confirmed." Header shows "Revision 68" after autosaves; whether autosave should count as a revision is a question for Bernie. | onboarding screenshot 12:13 |
| 3 | The draft is created with all fields blank and no examples or guidance. The AI panel is collapsed by default (an earlier assumption in this draft that it was open was wrong). Day headings update to show the lesson title after saving. | curriculum screenshots 13:17, 13:26 |
| 4 | Saving requires a reason; saving creates Version 2 with the banner "New draft version saved; review must be requested again." Day 1 was saved with minutes totalling 50 although the page says "Combined time must be 15-30 minutes"; no warning appeared at save time. | curriculum screenshot 13:26 |
| 5 | "Validate all blocks and request review" fails with one unbroken paragraph of roughly 40 lines at the bottom of an 11,748 px page, listing every missing item by block and date with no line breaks or links. Day 1 (2026-10-29) was flagged only for minutes ("must total 15-120 minutes and fit the agreed plan budget"). The editor says 15-30 minutes while this message says 15-120, and it does not say which condition failed. The banner at the top still shows the earlier save message. | curriculum screenshot 13:35 and pasted error text |
| 6 | Version history lists "Version 2 · draft" and "Version 1 · superseded" with the saved reasons; this is clear. | curriculum screenshot 13:35 |
| 7 | Keyboard test (Tab key, desktop browser): focus is always visible, order is logical top to bottom, every control including block tabs, AI fold, day folds and Save is reachable, and there is no keyboard trap. It takes 34 Tab presses to reach "Save new draft version" from the top of the page. | Saturnino's manual test |
| 8 | Narrow screen (Chrome device emulation, iPhone 16 393x852 and a 381 px capture; not a real phone): no horizontal overflow, sidebar collapses to a hamburger drawer, block tabs wrap to 2x2, fields are single column, buttons are tappable. Drawer closes on tapping the dimmed area and on Esc, focus returns to the hamburger button on close, and the first Tab after opening lands on "Overview". Header chrome (client switcher, user, sandbox banner) takes roughly the first screenful, the page is about 4,456 px tall, and Save is at the very bottom with no sticky control. | `curriculum-...-14_11_30.png`, `Screenshot_11.png` |
| 9 | Member sign-in as Sam first returned a bare "503 SERVICE UNAVAILABLE" page. Cause confirmed by reading code and the local database: Sam is a seeded imported user whose permissions were last refreshed at setup (about 4.7 hours earlier) and the freshness limit is 300 seconds, so access is denied by design. The page does not say why or what to do. Sam's local timestamp was refreshed twice (synthetic local data) to continue. | `Screenshot_12.png`; `Access::fresh`, `training.permission_freshness_seconds` = 300 |
| 10 | Member's enrolment page shows developer-style items: "Check background processing" with a "Queue foundation check" button, "Primary-platform sync: linked", and a "Background receipts" panel that "Refreshes every 5 seconds". The enrolment shows "Version 1" while the curriculum is Version 2, and "4 weeks" where setup uses "blocks". The upload form defaults to "Sensitive assessment for coordinator review". Member sidebar correctly lacks "Review queue". | enrolment screenshot 14:38 |
| 11 | Member can open "View curriculum and versions" and read the **draft** curriculum (Version 2, "Draft") including the coordinator's Day 1 lesson text. The page is read-only (no Save, no AI panel, no review section) but it is the full editor layout with greyed empty fields, and shows coordinator wording such as "Future adaptation cutoff" and "Review onboarding or reschedule". The banner says draft content is not approved learning material. The plan (M4 acceptance) says the member sees only the approved plan, so this needs Bernie's decision on whether member access to drafts is intended. | curriculum screenshot 14:41 |
| 12 | Member "Today and learning plan" with nothing approved is clear: "Lessons become available after the coordinator activates an approved programme." and "No submissions yet." A button reads "Progress, KPIs and future support", which is vague. | learn screenshot 14:41 |

Still not checked: colour contrast, screen-reader behaviour, the Compare view's readability, a real phone, and the AI generation flow (workers not started).

Suggested design directions for Bernie's review (proposals only, no contract change): group validation errors by block and day in a short list at the top with links to each field; disable or explain "Create curriculum draft" until the assessment is confirmed; show named competency/KPI selectors in place of numbers; reconcile the 15-30 and 15-120 wording; add a skip link or sticky save control; collapse unchanged rows in Compare.

## 5. Two-client fixture proposal (for Bernie to review; not run)

Bernie already shipped an opt-in, deterministic `RealisticDemoSeeder` (handoff `2026-10-27-M1-BERNIE-realistic-demo.md`): three fictional clients, 30 people, 9 groups, 21 enrolments, one multi-client member, one departed member. It requires an empty disposable database under `.runtime/realistic-demo*/demo.sqlite`. I have **not** run it. The default setup seed (`ClientAuthoritySeeder`) is single-client (`admin`, `coordinator-a/b`, `member-a/b`).

Proposal: reuse that seeder rather than create a parallel fixture, and for UI work agree two of its clients as the standard pair, checking that between them they cover these screen states:
- A member with no plan, a draft plan, a plan awaiting review, an approved plan and a completed programme.
- A coordinator assigned to two teams in one client, plus the same view in the second client (visual proof of separation).
- A member who belongs to two clients (client switch) and a departed member (access removed, history kept).
- A suspended client and the branded theme of each client (light and dark).
- Denied/empty states: member opening a peer's enrolment, coordinator opening an unassigned member, cross-client URL.

Open question for Bernie: which two clients, and whether the seeder will be extended for the new assessment, weekly-report, notes and chat states once those contracts exist.

## 6. Permission/negative-path evidence

None produced in this lane. The test suite's own permission tests passed in the runs above; their coverage was not reviewed.

## 7. Blocked / not-run / dependencies

- MariaDB suite: blocked on installing MariaDB and `pdo_mysql` locally (owner: Saturnino, optional; CI covers it).
- Browser/accessibility run: not run; needs `npm.cmd ci` and Chrome.
- Visual review of the screens listed in section 4: not started.
- D01-D10 and C01-C07 remain open (owner: project owner and Bernie). Particularly relevant to UI: 30-calendar vs 30-working days, next-week vs N+2 adjustment, multi-client staff, team vs cohort, export format.
- No cost or live-operation authorisation needed or used.

## 8. Remaining work and requested actions

Remaining for this task: visual walk-through of the member and coordinator journeys against the isolated app; annotated flows for the Development Planning UI/UX improvement; review of Bernie's client/team/grant and calendar proposals from the user-journey side; optional MariaDB and browser runs.

- Bernie: confirm the Development Planning UI/UX scope (design only, or design plus implemented slices), choose the two fixture clients, and say whether `origin/main` (`7d09283`) should be merged before further work.
- Saturnino: review this draft and the `SATURNINO.md` claim before treating either as his record.
- Project owner: no action requested from this handoff.

- Shared files released or still reserved: none touched or reserved.
- Actual hours: 2h, as reported by Saturnino on 2026-10-29 (his own figure; not measured by tooling). Remaining estimate: not assessed.
- Technical self-review status: not applicable to this lane's documentation (Bernie-owned).
- UX/accessibility review status: **not done**.
- Project-owner milestone acceptance: pending.
