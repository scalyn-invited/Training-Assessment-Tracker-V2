# Feature checklist

This is the primary development tracker. Define one bounded feature and its acceptance criteria before work. Complete implementation → test / QA → fix and retest → record evidence → verify completion before starting another feature. If blocked, record the blocker and seek direction before starting another feature. A checklist entry or old milestone is not evidence of completion.

Use **Not Started**, **In Progress**, **Blocked**, or **Completed** for feature entries. Completion requires all applicable checks, fixes, evidence and review/acceptance gates. If no issues are found, record “None found” under fixes. Mark checks not run or blocked explicitly; do not claim a pass. Record the revision or working-tree state and link to existing test output, screenshots or logs where relevant. Never invent reviews or owner acceptance.

## Feature inventory

### First-install browser wizard — In Progress

- **Acceptance:** fresh localhost installations configure an existing MariaDB database, a personal Global Admin and full password-login demo data; HTTPS hosted installations require a private server-issued token and create an empty MariaDB/MySQL workspace. Completed installations reject setup GET/POST requests, including during database outages. Legacy SQLite upgrades preserve configuration, keys, database files and private evidence.
- **Scope:** coordinated shared bootstrap, migrations and configuration changes authorised in this session; branch `codex/first-install-wizard`. Commit, push and draft PR authorised; merge and deployment remain separate.
- **Implemented:** four-step installer with encrypted file sessions, CSRF, hosted HTTPS/token access, connection/ownership checks, staged demo seeding, personal Global Admin, configuration backups and permanent lockout. Defaults/documentation now select MariaDB and password login; legacy synthetic tools explicitly select mock mode. Existing SQLite users have a guarded upgrade command. Working tree based on `dab27bf`; no existing user database or account migration was performed.
- **Installer QA:** MariaDB 10.4.32 local integration passed 12 tests / 82 assertions ([receipt](../../platform/.runtime/installer-checks/installer-local.xml)); hosted-empty integration and interrupted configuration-save recovery passed 13 tests / 62 assertions ([receipt](../../platform/.runtime/installer-checks/installer-hosted.xml)). Final guard suite passed 13 tests / 46 assertions, with its separately exercised database integration test opt-in/skipped ([receipt](../../platform/.runtime/installer-checks/installer-guards.xml)). The real `php artisan serve` startup/reload smoke passed: sign-in 200, setup 404 ([receipt](../../platform/.runtime/installer-checks/serve-smoke.json)). These local receipts are ignored runtime artifacts, not remote CI evidence.
- **Browser evidence:** a fresh local installation produced three clients, 30 fictional accounts, 21 training scenarios and one personal Global Admin. Personal administrator and seeded Client Admin password sign-in passed; populated-database refusal and setup GET/POST 404 lockout were observed. Requirements, database, administrator and completion surfaces had zero axe WCAG A/AA violations in the checked states. Desktop and 390px mobile visuals were inspected; completion had no horizontal overflow. [Desktop completion](../../platform/output/playwright/installer-complete.png), [mobile completion](../../platform/output/playwright/installer-mobile.png), [mobile administrator form](../../platform/output/playwright/installer-admin-mobile.png).
- **Regression/quality:** existing password suites passed 30 tests / 364 assertions. Broad SQLite run: 241 tests, zero assertion failures, seven GD-extension errors and five skips ([receipt](../../platform/.runtime/installer-checks/regression.xml)); affected branding suites then passed 32 tests / 394 assertions with GD enabled for that process ([receipt](../../platform/.runtime/installer-checks/branding-regression.xml)). Four document/OCR/ClamAV-dependent cases remain skipped; the installer integration skip was covered separately above. Pint on changed PHP, Composer validation, Blade compilation, JavaScript syntax, diff whitespace and the isolated synthetic restore drill passed.
- **Fixes:** corrected demo transaction capture; split lengthy seeding into 25 resumable steps; isolated test storage; preserved special-character database passwords; guarded lost metadata, process environment overrides and credential changes during resume; prepared legacy APP_ENV for artisan serve and made final configuration saving recoverable across server reloads. Preview-only namespace/package-discovery issues were corrected in the isolated harness. No global PHP configuration changes were made.
- **CI follow-up:** [PR #20](https://github.com/scalyn-invited/Training-Assessment-Tracker-V2/pull/20), initial head `2605d4d`: all three installer matrix jobs, PHP/SQLite, MariaDB concurrency and document checks passed. Browser fixture preparation failed because its separate UI configuration omitted explicit mock authentication. Added `TRAINING_AUTH_MODE=mock` to that isolated fixture; JavaScript syntax and exported environment checks passed. Full CI rerun pending on the fix commit.
- **Remaining gates:** No live hosted deployment/TLS/proxy qualification is claimed. Bernie technical self-review, Saturnino UX/accessibility review and owner acceptance are not recorded. Status remains **In Progress** pending those applicable gates. Merge and deployment remain separate.

### PDF implementation review - 9 November 2026

Source: [Training Assessment Tracker V2 PDF](<../Training Assessment Tracker V2.pdf>), all 26 pages, sections 1-37. Reviewed against local HEAD `fa2937512d5e47384c331aaa8523a087e39cc4bd` and the existing working tree. Documentation/output changes already existed; this review changes only this checklist. The [V2 requirement map](REQUIREMENTS.md) remains the scope cross-reference.

**How to read this checklist:** `[x] Implemented` means the stated bounded capability is present in local code (or the named planning artifact exists). `[ ] Partial` means foundations exist but the PDF requirement is not fully delivered. `[ ] Pending` means no dedicated implementation was found in the inspected application. These are implementation findings, separate from the feature workflow statuses above. A checked implementation item does **not** mean QA, human acceptance, live integration or production release is complete. Suggested/potential PDF options are identified as such; they are not silently promoted to mandatory scope.

**Verification limits:** This is a source/document review, not a new application test run. Referenced tests are existing coverage, not newly passing results. No browser, live AI, Cerebru, SMTP, deployment or pilot checks were run. No new technical self-review sign-off, UX/accessibility acceptance or project-owner acceptance is claimed. Pending findings were checked across routes, services, models, migrations and views; older milestone labels were not used as proof of implementation.

### Clients, people, roles and branding

| Checklist | PDF sections / V2 mapping | Current evidence | Remaining work or qualification |
| --- | --- | --- | --- |
| [x] Implemented: multiple clients, client membership, teams/cohorts and coordinator allocations | 3-5, 7; V2-01 | [ClientAdministration](../../platform/app/Services/Tenancy/ClientAdministration.php), [ClientFoundationTest](../../platform/tests/Feature/ClientFoundationTest.php) | Requalify actual client/team mappings and business acceptance; Cerebru relationships are separate below. |
| [x] Implemented: Admin, Coordinator and Member access with client/team/member scope | 3-4, 25; V2-02 | [ClientAccess](../../platform/app/Services/Tenancy/ClientAccess.php), [Access](../../platform/app/Services/Access.php), [permission integration tests](../../platform/tests/Feature/ClientPermissionIntegrationTest.php) | Existing explicit roles/grants support extension in code; an arbitrary custom-role designer is not established or required by this finding. New modules must use the same checks. |
| [x] Implemented: local account/member administration and client/team assignment | 3, 7; V2-01/02 | [LocalAccounts](../../platform/app/Services/LocalAccounts.php), [Administration](../../platform/app/Services/Administration.php), [ClientAdministration](../../platform/app/Services/Tenancy/ClientAdministration.php), [account tests](../../platform/tests/Feature/LocalAccountsTest.php) | Complete member journey still depends on the plan/progress/growth features below. Account-creation authority remains permission restricted. |
| [x] Implemented: client logo, name, application title and brand colours | 5, 24; V2-12 | [BrandingController](../../platform/app/Http/Controllers/BrandingController.php), [Branding](../../platform/app/Services/Branding.php), [SavedBrandingTest](../../platform/tests/Feature/SavedBrandingTest.php) | Local persistence and scope are present; final client brand and UX acceptance remain separate. |
| [ ] Pending: optional custom terminology and client-wide training programme settings | 5, 24; V2-12 | Current [branding form handling](../../platform/app/Http/Controllers/BrandingController.php) saves identity/appearance, not terminology or a client-wide programme configuration module. | Confirm which potential options are required, then define fields and behaviour. Per-enrolment onboarding is not the same capability. |

### Cerebru integration

| Checklist | PDF sections / V2 mapping | Current evidence | Remaining work or qualification |
| --- | --- | --- | --- |
| [ ] Partial: authentication and navigation as a Cerebru sub-application | 1, 6, 25; V2-03 | [SessionController](../../platform/app/Http/Controllers/SessionController.php), [hosted-password implementation](../../platform/docs/HOSTED_PASSWORD_LOGIN.md), [decisions](DECISIONS.md) | Interim email/password access exists; actual shared Cerebru authentication, SSO/MFA and navigation integration are not established. Hosted implementation is not deployment evidence. |
| [ ] Partial: import/sync users, members, clients, teams, permissions and relationships | 6-7, 25; V2-03 | [SandboxDirectory](../../platform/app/Services/SandboxDirectory.php), [Promotions](../../platform/app/Services/Promotions.php), [PrimaryPlatform](../../platform/app/Services/PrimaryPlatform.php), [IntegrationBoundaryTest](../../platform/tests/Feature/IntegrationBoundaryTest.php) | Local contract/sandbox foundations exist. `PrimaryPlatform` throws `primary_not_configured`; obtain actual API/source ownership and implement/qualify the live coordinator import/sync journey. |

### Assessments and coordinator approval

| Checklist | PDF sections / V2 mapping | Current evidence | Remaining work or qualification |
| --- | --- | --- | --- |
| [x] Implemented: create/publish reusable assessments and assign them to members | 8-9; V2-04 | [InitialAssessments](../../platform/app/Services/InitialAssessments.php), [AssessmentController](../../platform/app/Http/Controllers/AssessmentController.php), [InitialAssessmentTest](../../platform/tests/Feature/InitialAssessmentTest.php) | Business assessment content/rubrics and human workflow acceptance remain outstanding. |
| [x] Implemented: written responses, member context, document uploads and submission | 8-9, 20; V2-04 | [InitialAssessments](../../platform/app/Services/InitialAssessments.php), [WorkspaceController](../../platform/app/Http/Controllers/WorkspaceController.php), [DocumentExtractionService](../../platform/app/Services/DocumentExtractionService.php), [assessment tests](../../platform/tests/Feature/InitialAssessmentTest.php) | Files require clean scans and confirmed content for scoring. Actual scanner/parser environment qualification is separate from local code. |
| [x] Implemented: assessment status/history and immutable resubmissions | 7-9, 20; V2-04 | [InitialAssessments](../../platform/app/Services/InitialAssessments.php), [AssessmentAttempt](../../platform/app/Models/AssessmentAttempt.php), [assessment tests](../../platform/tests/Feature/InitialAssessmentTest.php) | Validate real-user usability; existing attempts and versioned evidence must remain preserved. |
| [ ] Partial: AI scoring with overall/category scores, evidence and explanations | 9-10, 20, 22; V2-05 | [AssessmentAnalysis](../../platform/app/Services/AssessmentAnalysis.php), `InitialAssessments::score`, [synthetic/manual tests](../../platform/tests/Feature/InitialAssessmentTest.php) | Score validation and evidence-linked feedback exist, but initial-assessment AI is explicitly restricted to local synthetic test members. Implement approved real-provider scoring and calibrate approved business rubrics. |
| [ ] Partial: explicit strengths, weaknesses, gaps and recommended focus areas | 10, 20, 22; V2-05 | [InitialAssessments scoring contract](../../platform/app/Services/InitialAssessments.php) accepts criterion scores, evidence references and feedback. | Criterion feedback can describe issues, but a dedicated validated strengths/gaps/recommendations output and downstream plan linkage are not delivered. |
| [x] Implemented: coordinator review, modification, approval/rejection and decision audit | 11; V2-06 | `InitialAssessments::decide`, [AssessmentAnalysis](../../platform/app/Services/AssessmentAnalysis.php), [assessment tests](../../platform/tests/Feature/InitialAssessmentTest.php) | Provisional results require human decisions; live AI and actual reviewer acceptance are separate gates. Review notifications use the shared notice service below. |

### Development plans and training

| Checklist | PDF sections / V2 mapping | Current evidence | Remaining work or qualification |
| --- | --- | --- | --- |
| [ ] Partial: generate a plan directly from the approved initial assessment | 12, 14; V2-07 | [Onboarding](../../platform/app/Services/Onboarding.php), [Generation](../../platform/app/Services/Ai/Generation.php), [InitialAssessments](../../platform/app/Services/InitialAssessments.php) | Legacy generation uses coordinator-confirmed onboarding findings. The approved initial-assessment contract explicitly returns `plan_creation_available: false`; implement the offered assessment-to-plan action and exact approved-result lineage. |
| [ ] Pending: explicit 30-day upskill plan | 13-14; V2-07 | [Onboarding](../../platform/app/Services/Onboarding.php) allows durations 4/6/8/10/12; [LearningCalendar](../../platform/app/Services/LearningCalendar.php) builds weekly blocks. | Resolve D04 calendar/working-day semantics and implement the distinct mode, partial final period and review flow. Four weekly blocks do not prove 30 days. |
| [ ] Partial: achievable workload and editable training allocations/activities | 13; V2-07 | [LearningPlans](../../platform/app/Services/LearningPlans.php), [LearningCalendar](../../platform/app/Services/LearningCalendar.php), [LearningTest](../../platform/tests/Feature/LearningTest.php) | Existing shared capacity, holidays, daily budgets and lesson editing are reusable. Complete explicit normal-work versus training presentation and the PDF's priorities/deadlines/milestones/resource editing for the 30-day plan. |
| [x] Implemented: review/edit/approve versioned legacy plans before member delivery | 14; V2-06/07 | `LearningPlans::review`, [Delivery](../../platform/app/Services/Delivery.php), [LearningTest](../../platform/tests/Feature/LearningTest.php) | This bounded approval mechanism exists; the PDF's complete assessment-derived 30-day approval journey remains partial through the rows above. |
| [x] Implemented: member lessons, activities, submissions and approved feedback | 3, 13, 32, 36; V2-07/15 | [Delivery](../../platform/app/Services/Delivery.php), [DeliveryController](../../platform/app/Http/Controllers/DeliveryController.php), [DeliveryTest](../../platform/tests/Feature/DeliveryTest.php) | Reuse for the new plan mode and qualify actual learning content and member usability. |

### Progress, reports and communication

| Checklist | PDF sections / V2 mapping | Current evidence | Remaining work or qualification |
| --- | --- | --- | --- |
| [ ] Partial: dedicated weekly progress submission and comparison with previous weeks | 15; V2-08 | [Delivery](../../platform/app/Services/Delivery.php), [Kpis](../../platform/app/Services/Kpis.php), [Progression](../../platform/app/Services/Progression.php) provide submissions, evidence and KPI foundations. | Add a weekly record/workflow covering completed/incomplete activities, time, results, challenges, comments, supporting documents and self-assessment, with period comparison. Lesson submissions alone do not fulfil this. |
| [ ] Partial: AI weekly analysis and coordinator-approved adjustments | 16, 22; V2-08 | [Progression](../../platform/app/Services/Progression.php), [AI task registry](../../platform/app/Services/Ai/Registry.php), [ProgressionTest](../../platform/tests/Feature/ProgressionTest.php) | Existing KPI suggestions and evidence-based N+2 adaptation are narrower. Implement weekly improvement/stalled/missed-target analysis; resolve D05 before changing protected N+2 behaviour to following-week adjustments. |
| [ ] Pending: development growth graph from baseline through follow-up | 17; V2-09 | [Dashboard](../../platform/resources/views/dashboard.blade.php), [Kpis](../../platform/app/Services/Kpis.php) inspected; no dedicated longitudinal growth chart found. | Define comparable approved metrics and render baseline/week/follow-up series with accessible alternatives. Decorative growth artwork is not a data graph. |
| [ ] Partial: role dashboards and scoped member/team/client reporting | 7, 17, 23; V2-09 | [WorkspaceController](../../platform/app/Http/Controllers/WorkspaceController.php), [PlatformAdministration](../../platform/app/Services/Tenancy/PlatformAdministration.php), [Kpis](../../platform/app/Services/Kpis.php), [ApprovedFeed](../../platform/app/Services/ApprovedFeed.php) | Basic workspace/admin views, assessment access and KPI projections exist. Complete weekly review, falling-behind/attention indicators, growth, new plan metrics and permission-scoped reports. PDF dashboard lists are potential content; confirm exact report scope under D07. |
| [ ] Pending: member notes sent directly to the coordinator | 18; V2-10 | No dedicated note model, service, route or view found in the application inventory. | Build note submission/history, assigned-recipient access and notification. Assessment context and submission comments are not a standalone member-note workflow. |
| [ ] Partial: SMTP notifications and reminders | 11, 18, 21; V2-10 | [LearningNotices](../../platform/app/Services/LearningNotices.php), [SendLearningNotice](../../platform/app/Jobs/SendLearningNotice.php), [NotificationAndScanTest](../../platform/tests/Feature/NotificationAndScanTest.php) | Queued notices, revocation checks, in-app/sink paths and SMTP code exist. Qualify actual delivery and confirm reminder/event coverage; notes and weekly-progress events depend on missing workflows. Relay acceptance alone is not inbox delivery. |
| [ ] Pending: basic training chatbot using the member's approved plan/context | 19, 22-23; V2-11 | No chat routes/models/views found; [Registry::TASKS](../../platform/app/Services/Ai/Registry.php) has no chat task. | Implement conversations, scoped approved-context retrieval, citations/history, injection handling and access rechecks. Provider adapters and a read-only training API are not a chatbot. |

### Shared AI, security, planning and full-product acceptance

| Checklist | PDF sections / V2 mapping | Current evidence | Remaining work or qualification |
| --- | --- | --- | --- |
| [ ] Partial: shared AI service covering all nine listed AI functions | 22; V2-05/07/08/11/13 | [Registry](../../platform/app/Services/Ai/Registry.php), [TaskGateway](../../platform/app/Services/Ai/TaskGateway.php), [AI provider tests](../../platform/tests/Feature/AiProviderTest.php) | Anthropic/Gemini/mock adapters, routing and budget controls exist. Assessment, 30-day planning, weekly analysis and chatbot gaps above prevent full coverage; approve provider/model/data/cost limits before live qualification. |
| [ ] Partial: secure, audited Cerebru-compatible application architecture | 25, 31, 35; V2-13 | [Access](../../platform/app/Services/Access.php), [Audit](../../platform/app/Services/Audit.php), [FileScanning](../../platform/app/Services/FileScanning.php), [Operations](../../platform/app/Services/Operations.php), [FoundationTest](../../platform/tests/Feature/FoundationTest.php) | Local isolation, audit, private-file and operational foundations exist. Validate actual Cerebru architecture, security/privacy obligations, deployment, backup/restore and live dependencies; this is not a security certification. |
| [x] Implemented artifact: written project concept, architecture inputs and detailed plan | 26-28, 37; V2-14 | Source PDF; [product architecture](../../archify-upskilling-20260929-181205/inputs/02_PRODUCT_ARCHITECTURE_SPEC.md), [architecture prompt](../../archify-upskilling-20260929-181205/inputs/01_AUTOPILOT_PROMPT.md), [implementation plan](IMPLEMENTATION_PLAN.md) | Artifacts exist; this does not establish complete current-scope content or historical review acceptance. Current PDF/decisions take precedence over legacy specifications. |
| [ ] Partial: reviewed Archify architecture | 29; V2-14 | [Archify handoff](../../archify-upskilling-20260929-181205/HANDOFF.md), [validation report](../../archify-upskilling-20260929-181205/VALIDATION_REPORT.md) | Generated package exists; outstanding visual/layout review and reconciliation to current scope cannot be treated as accepted architecture. |
| [x] Implemented artifact: repository, runnable application and implementation/test workflow | 26, 30-31; V2-14 | [platform README](../../platform/README.md), [AI workflow](AI_WORKFLOW.md), [coordination rules](COORDINATION.md), application source/tests | Controlled routine development and high-impact gates are documented. Current environment approval enforcement and every historical CLI operation were not audited. |
| [ ] Partial: resolve product, integration and operational questions | 35; V2-14 | [decision register](DECISIONS.md), [contracts](CONTRACTS.md), [cost approvals](COST_APPROVALS.md) | Some bounded implementation decisions are recorded. Actual Cerebru contracts, business scoring criteria, duration, adaptation/report scope, AI/SMTP and release inputs still need their applicable decisions. |
| [ ] Partial: complete MVP and continuous development cycle | 1-2, 32, 36; V2-15 | Implemented capabilities and gaps above; [PilotWorkflowTest](../../platform/tests/Feature/PilotWorkflowTest.php) covers a narrower synthetic legacy workflow. | Complete Assess -> Analyse -> Plan -> Train -> Measure -> Adjust with the PDF's 30-day, weekly, growth, notes, chatbot and actual integration requirements. No full PDF MVP acceptance claimed. |
| [ ] Partial: one-member end-to-end demo, feedback and iteration | 33-34, 37; V2-15 | [RealisticDemoTest](../../platform/tests/Feature/RealisticDemoTest.php), [demo guide](handoffs/2026-10-27-M1-BERNIE-realistic-demo-guide.md) | Existing synthetic demonstrations do not cover every PDF step. Show problem/users/solution, one realistic member, AI explanations and human approvals, weekly changes, graph and branding; identify planned portions honestly, then obtain feedback/acceptance. |

### Remaining implementation checklist

Select and define **one bounded feature at a time** using the template below. This list records dependencies, not an instruction to start all work or a delivery-date commitment.

- [ ] Complete real assessment scoring/explanation and the validated strengths/gaps output, with approved rubrics and provider qualification.
- [ ] Connect an approved initial assessment to plan creation; implement the explicit 30-day mode after D04 is resolved.
- [ ] Complete weekly progress capture, comparison and AI recommendations, preserving N+2 until D05 authorises a change.
- [ ] Build the growth graph and remaining role/reporting views using approved comparable evidence.
- [ ] Implement member notes and their coordinator notifications.
- [ ] Implement the permission-scoped training chatbot.
- [ ] Confirm optional client terminology/programme settings and implement the accepted scope.
- [ ] Implement/qualify actual Cerebru identity, import/sync and navigation once contracts and access are available.
- [ ] Complete agreed reminder/event coverage and qualify SMTP delivery.
- [ ] Run integrated functional, isolation, browser/accessibility and operational checks; fix and retest issues, record exact revision evidence, then obtain applicable technical/UX/owner acceptance and separate live/release approval.

No implementation feature was started by this documentation review. Existing workflow rules and the template below remain applicable.

## Feature entry template

Copy this section for each selected feature and add concise acceptance criteria before implementation.

### Feature: [Feature Name]

Acceptance criteria:
- [Define the expected behaviour and relevant boundaries.]

- [ ] Implementation completed
- [ ] Tests / QA completed
- [ ] Issues fixed and retested
- [ ] Evidence recorded
- [ ] Feature verified and completed

Status: Not Started

Evidence:
- Tests:
- Results:
- Fixes:
- References:
