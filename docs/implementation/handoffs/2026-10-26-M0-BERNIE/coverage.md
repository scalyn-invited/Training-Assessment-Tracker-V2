# PDF and implementation reconciliation

Inspected 2026-10-26 at source HEAD `8ce63129b5985c6edd86361df863ed960c999bfc`. Source: `docs/Training Assessment Tracker V2.pdf`, 26 pages. Text extracted from every page with pypdf; page 9 rendered and visually inspected to check the 30-day/workload/approval passage. This is a source-content review, not a visual audit of every PDF page.

## Evidence key

Paths below are repository-relative. Tests refer to existing code, not automatic acceptance. Fresh results are in [verification](verification.md). B01-B09 and October 12-13 handoffs are historical evidence only. In particular the present checkout has no `Branding.php`, `config/branding.php` or BrandingTest: do not count the separately delivered October 13 UI/branding branch as present merely because its handoff records PR #6.

| Key | Actual code inspected | Existing checks / remaining boundary |
| --- | --- | --- |
| A | `platform/app/Services/Access.php`, `Administration.php`, `platform/app/Models/User.php`, foundation migration | FoundationTest and AdministrationTest; organisation/environment filtering, fixed user role, sensitive flag, effective memberships/assignments. No explicit client grants or team/cohort type. |
| I | `platform/app/Services/MockIdentity.php`, `SandboxDirectory.php`, `PrimaryPlatform.php`, `ApprovedFeed.php`, `Promotions.php` | FoundationTest and IntegrationBoundaryTest; synthetic delegation, directory replay and feed scope. PrimaryPlatform throws `primary_not_configured`; no verified Cerebru API. |
| E | `platform/app/Services/Delivery.php`, `Grading.php`, `DocumentExtractionService.php`; document migrations | DeliveryTest, DocumentExtractionTest, DocumentParserTest; lesson attempts, provisional criteria/evidence scores, human review and scan-gated extraction. Not a distinct initial assessment definition/assignment module. |
| P | `platform/app/Services/Onboarding.php`, `LearningPlans.php`, `LearningCalendar.php`, `Ai/Generation.php` | LearningTest, AiGenerationTest, PilotWorkflowTest; resumable inputs, shared capacity, 4/6/8/10/12 weekly blocks, immutable approvals. No explicit 30-day mode. |
| G | `platform/app/Services/Kpis.php`, `Progression.php`, `LearningLifecycle.php` | ProgressionTest; approved-evidence lineage, insufficient evidence, protected N+2 publication and completion. No full weekly self-report/graph/report-export workflow. |
| N | `platform/app/Services/LearningNotices.php`, `platform/app/Jobs/SendLearningNotice.php`, notification controllers | NotificationAndScanTest and DeliveryTest; local receipts and SMTP boundary. Direct private notes and new weekly/assessment triggers remain missing. |
| Q | `platform/app/Services/Ai/TaskGateway.php`, `Registry.php`, provider adapters; `platform/config/ai.php` | AiProviderTest and AiGenerationTest; mocked/HTTP-fake provider checks and spend reservations. Dedicated chat not found in routes/services/models. |
| U | `platform/routes/web.php`, workspace/progress views and controllers, browser tests | Current dashboard shows scoped enrolments. No persisted client-brand editor, growth graph or complete client attention dashboard found. |
| O | `platform/docs/REMAINING_MILESTONES.md`, operations/deployment/pilot docs, `.github/workflows/ci.yml` | OperationsTest, PilotWorkflowTest; existing operational harnesses and historical CI. Live restore, release and human calibration remain separate. |

## Every PDF section

Each row identifies source page, requirement ID, reusable evidence and bounded gap/ticket. Rows describing governance are not feature-completion claims.

| Section / source pages | V2 IDs | Reuse | Missing scope, conflict or evidence / ticket |
| --- | --- | --- | --- |
| 1 Project overview / 1 | 01,03,06,14 | A/I/P | Multi-client foundations and parent architecture still need decisions; T01/T02. |
| 2 Problem / 1-2 | 07,08,09,11,15 | P/G | Complete measure/adjust/chat story absent; T05-T09. |
| 3 Who it helps / 2-3 | 02,04,06,10,11 | A/E/N | Assigned assessment, notes and chat gaps; T01/T03/T07/T09. |
| 4 Roles / 3 | 02 | A | Fixed role string is not extensible scoped grants; D02, T01. |
| 5 White-label clients / 3-4 | 01,12 | A/U | Organisation exists; configurable per-client experience unproven in this HEAD; T01. |
| 6 Cerebru integration / 4-5 | 03 | I | Actual schemas/auth/sync/navigation unavailable; D03, T02. |
| 7 Member management / 5 | 01,02,03 | A/I | Local enrolments/cohorts reusable; source teams/client assignment/editing and sync need accepted model; T01/T02. |
| 8 Assessment management / 5 | 04 | E | Lesson submissions/extraction not initial assessment assignments; T03. |
| 9 Assessment workflow / 6 | 04,06 | E | Complete assigned assessment lifecycle missing; T03. |
| 10 AI assessment scoring / 6-7 | 05 | E/Q | Rubric/evidence validation reusable; category summaries, strengths/gaps/focus and initial assessment calibration need T04. |
| 11 AI approval / 7-8 | 06 | E/N | Lesson review exists; initial assessment review/notifications/reject terminology need T03/T04/T08. |
| 12 Assessment-informed plans / 8 | 07 | P | Confirmed manual/extracted onboarding exists; approved assigned-assessment linkage and creation entry point need T05. |
| 13 30-day upskill plan / 8-9 | 07 | P | Weekly blocks are not 30 days; workload display and final period depend on D04; T05. |
| 14 Plan approval / 9-10 | 06,07 | P | Exact-version human approval reusable; apply to new duration/source lineage; T05. |
| 15 Weekly progress / 10 | 08 | G/E | Lesson work is not weekly activity/time/challenge/self-report submission; T06. |
| 16 Weekly AI analysis / 10-11 | 08 | G/Q | Following-week wording conflicts with N+2 publication; D05, T06. |
| 17 Growth graph / 11 | 09 | G/U | No complete accessible growth series and scoped reports; T07. |
| 18 Member notes / 11-12 | 10 | N | Dedicated private member-to-coordinator notes absent; T08. |
| 19 Training chatbot / 12 | 11 | Q | No conversation/context/citation workflow; T09. |
| 20 Written assessments / 12-13 | 04,05 | E | Text/files reusable; assignment/history/context and initial scoring need T03/T04. |
| 21 SMTP / 13 | 10 | N | New event triggers and authorised live delivery qualification; T08/T10. |
| 22 AI integration / 14 | 05,11,13 | Q/E/P/G | Current mock purposes cover some generation/grading/adaptation; weekly/chat and qualified live behavior remain T04/T06/T09/T10. |
| 23 Dashboards / 14-15 | 09,11 | U/G | Scoped enrolments reusable; attention queues, comparable charts and chatbot panel need T07/T09. |
| 24 Client branding / 15 | 01,12 | U/A | Historical UI branch differs from this source; verify integration and persisted scoped settings; T01. |
| 25 Architecture / 15-16 | 01,02,03,13 | A/I/O | Parent/client contracts and full isolation qualification missing; T01/T02/T10. |
| 26 Development workflow / 16-17 | 14 | O | Guides/repository already exist; reuse rather than greenfield rebuild; T11. |
| 27 Initial concept / 17 | 14 | implementation plan | Existing concept/requirements are inputs, not human acceptance; T11. |
| 28 Master prompt / 17-18 | 14 | AGENT and milestone prompts | Reconcile PDF additions and decisions through these proposals; T11. |
| 29 Archify / 18 | 14 | outer architecture package | Architecture is a reference; historical blocked layouts/browser checks not resolved here; T11. |
| 30 Repository/CLI / 19 | 14 | current Git and application | Existing repo usable; branch/PR/CI gates remain evidence-driven; T11. |
| 31 Approval strategy / 19-20 | 13,14 | current guardrails | Routine authorised local checks allowed; no live/cost/release approval implied; T10/T11. |
| 32 Initial MVP / 20-21 | 15 | all partial reusable areas | Missing features prevent full MVP claim; T01-T10. |
| 33 Demo structure / 22-24 | 15 | PilotWorkflowTest | Accelerated synthetic lesson pilot lacks new assessment/weekly/chat/30-day flow; T10. |
| 34 Demo expectations / 24 | 15 | browser/PilotWorkflowTest | Distinguish completed/planned; representative human demo/acceptance pending; T10. |
| 35 Open questions / 24-25 | 13,14 | open D01-D10 | 25 source questions remain actual discovery/product inputs; see packets/checklist; T11. |
| 36 Product principle / 25 | 15 | P/E/G | Human oversight preserved; full continuous development loop remains partial; T10. |
| 37 Proposed next step / 26 | 14 | existing plan/architecture/repo | Historical initial-spec instruction does not require rebuilding existing app; reconcile/review current scope; T11. |

## Requirement-ID index

The full identifiers are V2-01 through V2-15 (the section table abbreviates their numeric suffixes).

| ID | Evidence | Tickets / acceptance still required |
| --- | --- | --- |
| V2-01 | A/U | T01/T02; two-client hierarchy and branding |
| V2-02 | A | T01; grants, cross-client/team/worker/download denial |
| V2-03 | I | T02; actual sandbox identity/sync/revocation |
| V2-04 | E | T03; initial assessment assignment and submission |
| V2-05 | E/Q | T04; explainable calibrated assessment scoring |
| V2-06 | E/P | T03-T05; pinned human decisions and audit |
| V2-07 | P | T05; accepted explicit 30-day mode |
| V2-08 | G | T06; weekly report/comparison/adaptation policy |
| V2-09 | G/U | T07; comparable growth and scoped reports |
| V2-10 | N | T08/T10; notes, triggers, live delivery |
| V2-11 | Q | T09; grounded scoped chat |
| V2-12 | A/U | T01; persisted scoped client settings |
| V2-13 | A/Q/O | T01-T10 cross-cutting security/operations |
| V2-14 | existing guides/O | T11/T02; source/decision/contract workflow |
| V2-15 | PilotWorkflowTest/O | T10; integrated story and real pilot |

## Structural inventory and migration risks

- `users.organisation_id` holds one organisation, `role` one string, `sensitive_access` one Boolean. This is not a cross-client grant ledger. Admin currently sees all otherwise eligible organisation enrolments; do not interpret that as approved personal-assessment access in the future model.
- Identities have unique issuer/subject mapped to one user. Users have a global email uniqueness baseline and per-organisation/environment external-person uniqueness. Cross-client staff and source-ID mapping need D01/D02/D03 before changing constraints; email cannot become an identity link.
- Groups contain organisation/environment, origin and active state but no explicit team/cohort discriminator or external group ID in the foundation table. Memberships and coordinator assignments are separate effective-dated rows, with unique group/user pairs; repeated historical intervals and typed teams need deliberate migration design.
- Enrolments name member, group and accountable coordinator; files are private and tied to enrolment. Ordinary foreign keys alone do not prove same-client relationship constraints. Expand validation/constraints with negative tests after decisions.
- Seed data contains one organisation, two groups and five synthetic users. The existing cross-organisation mutation test is useful, but does not satisfy the richer two-client/two-teams-each foundation gate.
- Future migration rehearsal must use backups and an isolated MariaDB restore: additive mapping/grant tables first, validate orphan/duplicate/cross-client references, preserve IDs/approved history/evidence hashes, then enforce constraints. Do not silently move training history during client transfer. No migration is executed by M0.
