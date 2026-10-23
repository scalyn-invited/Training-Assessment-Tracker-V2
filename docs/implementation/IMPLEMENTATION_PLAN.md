# Named two person implementation plan

> Detailed product plan retained; AI-assisted resource and responsibility model revised 23 October 2026. Bernie is Developer A and technical lead; Saturnino is Developer B, UI and Design. Use STATUS.md for implementation progress.

[Original formatted Word plan](<../Training Assessment Tracker V2 Implementation Plan Two Developers.docx>)  |  [Current AI-assisted resourcing memo](AI_ASSISTED_TWO_DEVELOPER_DELIVERY_PLAN.docx)


## Training Assessment Tracker V2

Detailed implementation plan for two contributors

Prepared 9 October 2026  |  Resourcing and responsibility model revised 23 October 2026  |  Version 1.3

### Delivery recommendation

Establish the client, team and member structure, enforce shared permissions, and agree the Cerebru integration contracts before expanding feature work. Then complete the assessment-to-development workflow, weekly reporting and training chatbot on those foundations. Reuse the existing local application rather than restart it.

Bernie (Developer A) is the existing developer and technical lead. Saturnino (Developer B, UI and Design) provides implementation-ready user flows, screen designs and bounded UI coding after shared contracts are stable. Bernie owns core implementation, technical checks and documented self-review. Saturnino reviews UX/accessibility; the project owner accepts locally completed milestone outcomes.

| Planning measure | Baseline |
| --- | --- |
| Delivery approach | AI-assisted core implementation and technical self-review led by Bernie; UI/design and bounded presentation work led by Saturnino; project-owner milestone acceptance. |
| Build and local acceptance target | 3–5 M0–M7 milestone work packages per week; about 2–3 weeks from an agreed start. Provisional and unvalidated. |
| Schedule dependency | Mandatory end-of-week-one reforecast, actual accepted work and dependency readiness. Live integration, human pilot, the full 30-day learning cycle and production release are outside this target. |
| Weekly resource commitment | Bernie: 8h 06m core development/checks + 1h 30m coordination/self-review = 9h 36m (80%). Saturnino: 2h flexible UI design/small UI implementation + 24m coordination = 2h 24m (20%). Combined: 12h. |
| Prior effort baseline | 640 implementation hours (328 Developer A, 312 Developer B), already AI-assisted; superseded estimate retained for history, not actual hours. |
| Commercial status | No new paid service, licence, subscription or infrastructure commitment is authorised by this plan. |

### Scope and release boundary

The MVP includes multi-client branding, member and team management, Cerebru import/sync, assigned written assessments and uploads, explainable provisional AI scoring, coordinator decisions, a 30-day plan, weekly progress and recommendations, growth graphs, member notes, notifications and a basic scoped chatbot. Production release requires live identity, integration, provider and operational evidence; a synthetic demo does not satisfy these gates.

### How to use this plan

Use the sections below for scope and contracts, coordination, the overall timeline, detailed milestone work packages, access and acceptance, dependencies, cost approval and first actions. Reforecast after M0 and the foundation gate.

## 1 Source coverage and current implementation

The 26-page PDF in docs is the product scope authority for this update. The user’s latest instructions add two-person coordination, explicit client/team/member access limits, an 80/20 responsibility split and prior approval of additional costs. Existing code and ledgers establish reusable work, not proof that the new PDF scope is complete.

| PDF scope | Observed baseline and required change | Milestone |
| --- | --- | --- |
| Sections 1–7, 24–25<br>Clients, roles, Cerebru | Organisation and group fields exist. Add client lifecycle, branding, scoped grants and verified client/team mappings. Live identity remains pending. | M1–M2 |
| Sections 8–11, 20<br>Assessments and scoring | Learning submissions, grading and scan-gated extraction exist. Add a distinct assessment assignment workflow and complete category-level score review. | M3 |
| Sections 12–14<br>Development plans | Versioned block-based plans exist. Add an explicit 30-day mode, workload display and assessment-to-plan entry point. | M4 |
| Sections 15–17<br>Weekly progress and growth | KPI snapshots and N+2 adaptation exist. Add weekly reports, comparisons, growth charts and agreed adjustment timing. | M5 |
| Sections 18, 21<br>Notes and communication | In-app notices and SMTP boundary exist. Add direct member notes, weekly triggers and qualified live delivery. | M5, M7 |
| Sections 19, 22<br>Training chatbot and AI | Two provider adapters and mock exist. No dedicated chatbot located in reviewed service, route and view areas. Add scoped context and cost controls. | M3, M6 |
| Section 23<br>Role dashboards | Local administration and learner/reviewer screens exist. Extend dashboards with permission-filtered totals and attention queues. | M5 |
| Sections 26–31, 35, 37<br>Planning and engineering | Repository and application already exist. Reconcile architecture, decision log, contract checks and two-person guidance; do not repeat a greenfield setup. | M0–M2 |
| Sections 32–34, 36<br>MVP and complete demo | Synthetic learning pilot exists. Extend the story to PDF features and run a representative, authorised human pilot. | M7 |

### Evidence limits

Reviewed checkout: bdc55df. Sources include AGENT.md, the requirements and remaining-milestones ledgers, Access.php, Administration.php, User.php, PrimaryPlatform.php and foundation migrations. The existing admin role has broad organisation enrolment access; sensitive file access has a separate check. PrimaryPlatform deliberately throws primary_not_configured. Local UI/browser files already have uncommitted changes and must be preserved.

The ledger records October 5 hosted test results, including 113 MariaDB tests and seven browser tests. These were not rerun for this planning task. No live service, paid model call, deployment or production readiness was verified here.

## 2 Client structure and data foundations

### Proposed model and ownership

Use the existing organisation identifier as the client boundary unless Cerebru discovery proves it represents a different level. Avoid introducing a second competing tenant identifier. The business relationship is Client → Team → Member → Assessment → Development plan → Progress. A client may have many teams; a member has one active client for the MVP and may have explicitly granted team memberships within it.

Keep imported Cerebru teams distinct from training cohorts. Existing groups may represent either; add explicit type and external source mappings rather than treating every cohort as an organisational team. Cohorts cannot widen access beyond the client and authorised team/member assignment.

| Entity or boundary | Implementation rule |
| --- | --- |
| Client and configuration | Active state, stable external ID, name, approved logo/colours/title, terminology and versioned programme defaults. Client configuration must never change another client’s settings. |
| Identity and grants | Immutable issuer plus subject identifies the person. Separate sign-in identity from client-scoped role/capability grants. Record effective dates, source, permission version and revocation. |
| Teams and members | Client-scoped source IDs and effective memberships. Imported fields stay source-owned; local cohorts and reviewer assignments stay training-owned. |
| Training records | Assessments, plans, weekly reports, notes, chat sessions, report jobs and private files carry client/environment scope and member/enrolment ownership. |
| Data integrity | Prevent cross-client relationships using service validation and appropriate composite constraints/indexes. Do not rely only on UI filters or UUIDs being hard to guess. |
| Files jobs and caches | Private storage; reference-based queue payloads; client/member/permission-version cache keys; server-side access checks at execution and download time. |

### Migration and compatibility

Bernie (Developer A) owns the migration sequence. Inventory existing organisation, role, group and identity records; back up the development database; add fields and mapping tables before enforcing constraints. Backfill the existing organisation as an explicit client and validate row counts, orphan relationships, uniqueness and permissions. Preserve approved grades, evidence hashes, programme versions and audit history. Rehearse on MariaDB and an isolated restored copy before production migration.

Do not silently move historical training when a member changes client. End old access, create the new client membership, and require a separately authorised history-transfer decision. For the MVP, cross-client coordinators and shared login identities must either use explicit client grants and a validated client switch or be excluded by a recorded decision in M0; never infer access from email.

### Foundation exit gate

Two clients, at least two teams per client and several members must coexist in the same test instance with no cross-client reads, writes, files, counts, jobs or cache leakage. All later features consume the same authorised query and command services.

## 3 Permissions and visibility contract

Deny by default. Resolve the actor, active client, environment, role capabilities, effective team assignment and member/record ownership on the server. A URL, hidden field, chatbot prompt or selected dashboard client is not authority. An Admin title alone does not grant access to personal assessment data.

| Actor | Permitted scope | Restrictions |
| --- | --- | --- |
| System administrator | Manage platform settings, clients and grants; see approved operational totals. | Personal reports and assessments require an explicit client data capability. Support access is reasoned, time-limited and audited. |
| Client administrator capability | Manage members, teams, branding and permitted configuration within the selected client. | No other client. Sensitive assessments and identifiable reports need explicit capability; no automatic grading authority. |
| Training Coordinator | Assigned teams and accountable member/enrolment assignments; assessment and plan review; permitted weekly reports and notes. | Team membership alone is not coordinator authority. No unassigned members, self-approval or hidden reviewer material without permission. |
| Member | Own assignments, uploads, approved results and plan, own progress, notes and chat history. | No peers, team aggregates, answer keys, private reviewer notes or provisional grades by default. |
| Integration service | Approved directory operations or delegated read scopes under a registered client/audience. | Machine directory credentials never impersonate a member. Delegated access intersects current actor policy. |
| Background worker | Only the scoped task and data references it was authorised to process. | Recheck grants before processing, publication, export and notification; discard or hold work after revocation. |

### One policy for application reports and chat

Apply scope filters before pagination, totals, search, aggregation or AI context assembly. Report exports, chart tooltips, chatbot citations and direct downloads must enforce the same policy as the originating screen. Store source and permission versions on generated artifacts. Recheck access at download or response time, even if the artifact was authorised when queued.

### Revocation and sensitive data

Client suspension, team removal, member deactivation and coordinator reassignment invalidate sessions, caches and queued recipients. Preserve the existing five-minute permission freshness ceiling as a proposed maximum until Cerebru confirms the contract; stale imported permissions deny privileged/external retrieval. Keep sensitive-assessment access separate from ordinary reporting. A member may access their own clean supporting evidence without receiving unrelated sensitive material.

### Required negative tests

Test Client A against Client B, Team A1 against A2, member against peer, unassigned coordinator, revoked actor, stale permissions, forged client/member IDs, direct file URLs and cross-client foreign keys. Repeat the denial tests through HTTP, Livewire, worker execution, reporting and chatbot retrieval. Confirm no titles, counts or existence clues are returned.

## 4 Cerebru and shared integration contracts

Treat Cerebru as the parent application from the outset. Bernie owns discovery, adapters and technical verification; Saturnino designs and may implement bounded member/admin interface states against stable shared fixtures. No Cerebru endpoint, token format, permission claim or source field is assumed to exist until documented and tested.

| Contract | Required agreement and proof |
| --- | --- |
| Authentication and navigation | Confirm the actual IdP and Cerebru launch/return flow. If OIDC is supported, verify PKCE, state, nonce, issuer, audience, expiry, signatures, key rotation, MFA and revocation. The current mock is not production authentication. |
| Directory and hierarchy | Obtain schemas and sandbox samples for clients, teams, users, memberships, roles, relationships and deactivations. Agree immutable source IDs, field ownership, pagination, rate limits and conflict rules. |
| Import and ongoing sync | Provide preview, duplicate review, explicit confirmation, status and retry handling. Support full reconciliation plus agreed delta/webhook mechanism. Use event IDs, versions and tombstones; advance cursors only after persistence. |
| Local member linking | Keep manual local-only records usable internally. Link only after verified matching and acknowledgement. Reuse the original idempotency key after timeout; history export remains a separate decision. |
| Delegated questions and facts | Authenticate caller and user separately, intersect delegated scopes with active client/team/member rights, and return approved minimal facts with provenance. Directory tokens plus arbitrary user headers are invalid. |
| Failures and operations | Document timeout/retry budgets, replay protection, signature validation, stale-permission behaviour, audit correlation, monitoring and reconciliation. External outages must not produce duplicate members or grants. |

### Contracts that unblock parallel work

Freeze a small versioned contract set in M1: resolved actor/client context, authorised member selector, assessment result summary, programme version summary, weekly progress summary, approved chat context and report projection. Agree error semantics, idempotency, pagination, optimistic versions and audit events. Use repository schemas and synthetic fixtures; provisional contract names do not claim real Cerebru API support.

### Technical approach

Extend the current Laravel application, Blade/Livewire screens, MariaDB and database queues. Keep adapters behind application services; reuse the outbox and private file pipeline. Avoid a mandatory new vector database or reporting SaaS for the basic chatbot and reports. Validate the final deployment and identity choices against the actual Cerebru environment in M0–M2.

### Integration gate

Contract mocks allow feature development to proceed, but full integrated-MVP acceptance requires real sandbox evidence of allowed reads, denied reads, import, sync, deactivation, duplicate prevention and recovery. If the API is unavailable, publish a dependency delay and demonstrate the adapter locally without calling it integrated.

## 5 Working agreement for two contributors

| Area | Bernie — Developer A and technical lead | Saturnino — Developer B, UI and Design |
| --- | --- | --- |
| Foundations | Own client schema, grants, access services, migrations, architecture decisions and technical checks. | Design client/team/member and branding journeys; implement only bounded UI slices against stable contracts. |
| Cerebru and AI | Own identity, directory/delegation adapters, AI gateway, scoped chatbot retrieval and adversarial checks. | Design and optionally implement import/sync and chatbot presentation states, citations, errors and accessibility. |
| Learning workflows | Own assessment/plan/progress state transitions, versioning, approval, calendars, service wiring and functional regression. | Produce user flows and annotated states; implement small approved Blade/Livewire/CSS slices. |
| Reporting and release | Own authorised projections, export execution, operational security, technical qualification and deployment procedure. | Design dashboards/charts and pilot-facing material; review usability/accessibility and capture feedback. |
| Review and acceptance | Perform documented technical self-review against the exact revision. | Review UX/accessibility only; this is not independent technical approval. Project owner accepts milestone outcomes. |

### Weekly coordination

Reserve 1h 30m of Bernie's week for planning, integration, documented technical self-review and decisions. Reserve 24m of Saturnino's week for design/UI handoffs and alignment. Reforecast at the end of week one and whenever a dependency blocks the target cadence.

### Branches and shared files

Use one dedicated short-lived branch or isolated worktree for each independently reviewable feature or milestone task, based on the agreed shared main branch. Each contributor has a separate local database and worker configuration when running the application. Assign one writer at a time for access services, shared migrations, dependency locks, route registration, shared layouts and common fixtures. Bernie coordinates migration ordering and technical integration; Saturnino coordinates the design and bounded implementation of shared UI components. Integrate at least weekly and before the next dependent task begins.

A task ticket states PDF requirement, scope, owner, review type, dependency, estimate, acceptance scenarios and contract/schema changes. Each feature or milestone branch is delivered through a pull request linked to that ticket. The PR explains behaviour, lists acceptance evidence and actual checks, and includes the required CI results. Bernie records technical self-review of the exact revision; CI does not replace that review. Saturnino records UX/accessibility findings for relevant interfaces. If required CI is unavailable, record the gap as a blocker and agree an explicit exception before merge. Security, migration and integration changes require explicit review of failure and revocation cases; a green build alone is insufficient. The project owner provides milestone acceptance.

### Handoffs and conflict resolution

Publish schemas and fixtures before UI implementation. Bernie records technical self-review of the contract; Saturnino acknowledges the UI-consumer requirements in the ticket. Incompatible changes require affected technical and UI checks to be updated in the same integration window. Rebase/update before review, let the designated owner resolve shared-file conflicts, then rerun affected checks. Limit work in progress to one main task per contributor, plus urgent fixes.

### Decision and continuity ownership

Bernie coordinates technical decisions and self-review; the project owner approves locally completed milestone outcomes, scope, budget and release. Both contributors keep the setup material relevant to their lane current. They are not substitutes for the human Training Coordinators needed to approve and calibrate training outcomes during the pilot.

## 6 Milestones and overall delivery timeline

The user-requested provisional AI-assisted build target is **3–5 of the eight M0–M7 milestone work packages reaching local acceptance each week**, or about **2–3 weeks** from an agreed kickoff. This is an unvalidated target, not a demonstrated forecast. At the revised weekly allocation, two to three weeks provide 24–36 project hours: 20h 12m–30h 18m development/design and 3h 48m–5h 42m coordination. Bernie contributes 19h 12m–28h 48m and Saturnino contributes 4h 48m–7h 12m. AI assistance was already included in the previous 640-hour estimate (328 hours for Developer A and 312 for Developer B); keep that figure as a superseded baseline, not as actual time or evidence of completed work.

| Weekly measure | Bernie | Saturnino | Combined |
| --- | ---: | ---: | ---: |
| Development or design, including checks | 8h 06m | 2h | 10h 06m |
| Coordination and self-review/handoff | 1h 30m | 24m | 1h 54m |
| Total project time | 9h 36m | 2h 24m | 12h |

The combined weekly target remains 12 hours but is now split 80/20 to reflect Saturnino's availability alongside client commitments. The allocation remains constant in weeks 1 and 2 and any optional week 3 spillover; no planned phase increase or taper applies. Reforecast after week one from actual accepted work and dependencies. The forecast covers AI-assisted building and local acceptance only. Live identity/Cerebru integration, live provider/SMTP qualification, human pilot, the full 30-day learning cycle and production release remain separate gates outside the 2–3-week target.

| Delivery week | Milestone work-package target | Parallel approach | Checkpoint |
| --- | --- | --- | --- |
| Week 1 | 3–5 of M0–M7 | Bernie leads core implementation; Saturnino prepares designs and bounded UI slices only after contracts are stable. | Record project-owner accepted items, defects and blockers. Reforecast in all cases; if fewer than three work packages meet local acceptance or a critical dependency blocks progress, revise the target. |
| Week 2 | 3–5 remaining | Continue independent work in parallel; integrate dependent work against reviewed contracts. | Record accepted, blocked and remaining items; assess whether local acceptance is complete. |
| Week 3, if needed | Remaining local fixes and acceptance | Use only for spillover from the provisional target; do not count live gates as completed. | Publish a revised forecast and list external/live dependencies separately. |

Milestone completion for this target means implementation and applicable local acceptance evidence. It does not mean that an external identity provider, Cerebru sandbox, paid AI service, SMTP service, human pilot or production release has passed its gate. No calendar dates are assigned until kickoff and actual availability are confirmed.

## 7 Shared delivery schedule and feature ownership

| Work package | Bernie ownership | Saturnino UI and design ownership |
| --- | --- | --- |
| M0 Scope reconciliation and onboarding | Code/requirements reconciliation, decision proposals, client/integration discovery and shared contract risks. | Journey/screen inventory, annotated UX acceptance flows and reproducible UI setup findings. |
| M1 Client structure, permissions and branding | Client/grant schema, migrations, access services, revocation, technical checks and stable contracts. | Client/team/member and branding designs plus bounded presentation-layer implementation. |
| M2 Cerebru identity, directory and integration | Identity/Cerebru adapters, source mapping, sync, revocation, failures and technical evidence. | Import-preview, status and conflict designs plus approved UI slices. |
| M3–M4 Assessment and development plans | Assessment and plan lifecycle, rubrics, approvals, calendar, service wiring and regression checks. | Assessment/plan user flows, annotated states and bounded Blade/Livewire/CSS implementation. |
| M5–M6 Progress, reporting and chatbot | Progress/notes services, projections/exports, retrieval policy, context restrictions and technical checks. | Dashboard/chart/chat designs, accessible alternatives and bounded interface implementation. |
| M7 Local qualification preparation | Technical qualification, security, migration, recovery and deployment-preparation evidence. | Usability/accessibility review, pilot-facing designs and feedback capture. Live pilot and release decisions remain outside this target. |

Bernie integrates the work and records technical self-review within his weekly allocation. Saturnino reviews relevant UX/accessibility and hands off bounded UI work. Publish shared contracts before UI consumers bind to them; use the established acceptance criteria and preserve milestone dependencies. The project owner accepts milestone outcomes. The current target is a build cadence, not permission to bypass a contract, security, human-approval or release gate.

## 8 Foundation milestone work packages

### M0 Scope reconciliation and onboarding

Shared planning target: component of the provisional 2–3-week build/local-acceptance window; see section 6. No calendar date or live integration completion is implied.

Bernie inventories the actual code and evidence against the current requirements, prepares D01–D10 decision proposals, identifies reusable B01–B09 work and maps client/identity/Cerebru risks. Saturnino reproduces the UI-relevant synthetic workflow, inventories user journeys and screen states, records interface setup gaps and proposes implementation-ready UX/accessibility flows. Bernie records stable contracts before Saturnino binds UI work to them.

Acceptance: both developers can reproduce the isolated synthetic workflow with dated revision and actual results; the requirements map distinguishes reuse from new M0–M7 acceptance; decisions, owners, dependencies and handoffs are recorded. This milestone does not establish live identity or production readiness.

### M1 Client structure permissions and branding

Shared planning target: component of the provisional 2–3-week build/local-acceptance window; see section 6.

Bernie owns client/identity/grant schema and migration, shared authorisation and revocation, contract publication, integrity checks and technical regression coverage. Saturnino designs client/team/member and branding screens and may implement bounded presentation-layer slices against stable contracts; he reviews responsive and accessible behavior without changing permission policy.

- Add client lifecycle and configuration, effective team/member grants and explicit coordinator assignments; prevent implicit privilege escalation from directory imports.

- Replace single role assumptions where needed with scoped capabilities. Keep the familiar Admin, Coordinator and Member UX without granting broad sensitive access.

- Apply authorised selectors to members, plans, evidence, notifications and AI entry points. Partition caches and background work by client and environment.

- Provide validated logo, colours, name and title settings with accessible contrast and safe asset handling. Branding is configuration, never arbitrary client code.

Acceptance: two-client tests deny cross-client read/write and aggregate access, team removal revokes access, sensitive capability checks hold, and all existing learning flows remain usable. A restored database can be migrated without losing history. B reviews A’s policy changes; A reviews B’s screen and input boundaries.

### Handoff to feature development

Publish the actor/client context and policy API, scoped member and team selectors, fixture factories, migration notes and approved error semantics. Neither reporting nor chatbot work may invent its own client filter or bypass this foundation gate. Later permission changes require both developers’ review and regression evidence.

## 9 Integration and assessment work packages

### M2 Cerebru identity directory and integration

Shared planning target: component of the provisional 2–3-week build/local-acceptance window; see section 6.

Bernie owns Cerebru delegation integration, directory mapping, idempotent sync, revocation, failure handling, contract checks and sandbox qualification. Saturnino designs import-preview/status/conflict screens and may implement bounded UI slices after the adapter contract is stable. Use the identity/Cerebru discovery and reviewed contracts from M0; usable API documentation and sandbox access are prerequisites. Local contract work may proceed without live access, but it must not be reported as integrated evidence.

- Prove sign-in, client selection, identity mapping and safe navigation to/from Cerebru. Disable mock authentication in production configuration.

- Import clients, teams and permitted members; show source-owned versus local fields; implement explicit duplicate/linking decisions and sync failure recovery.

- Test replay, out-of-order events, deactivation, key rotation, stale permissions and caller/actor separation. Record schema examples and real sandbox evidence.

Acceptance: authorised users see only their client/team/member data through both app and delegated API; removals invalidate access; repeated sync produces no duplicates; an outage recovers with the original event/key. If live prerequisites are absent, mark M2 contract-complete only and keep integrated release blocked.

### M3 Assigned assessments and explainable review

Shared planning target: component of the provisional 2–3-week build/local-acceptance window; see section 6.

Bernie owns the assessment assignment/submission lifecycle, rubric-bound AI analysis, human decisions, service wiring and end-to-end technical regression. Saturnino designs coordinator authoring/assignment, member response/upload and review states and may implement small approved UI slices; notification and permission behavior remains Bernie's responsibility.

- Create reusable assessment definitions, versioned questions/rubrics, assignments, deadlines, statuses and member history. Reuse submission/file primitives without pretending lesson submissions already cover standalone initial assessments.

- Allow written responses, uploaded results, supporting documents and member explanations. Preserve quarantine, malware scanning, bounded extraction and human confirmation.

- Show overall/category scores, rubric-linked evidence, a concise scoring rationale, strengths, weaknesses, gaps and focus areas. Explanations must cite evidence; hidden model reasoning is not required.

- Require Approve, Modify or Reject with actor/time/reason and exact versions. Preserve original AI output, edits, final decision and history. Notify the reviewer; publish only approved member-visible results.

Acceptance: complete assignment → member submission → provisional analysis → coordinator modification/approval → final result. Missing evidence is not zero; rejected output is not published; stale decisions conflict; a member cannot read a peer’s assignment or answer keys. Approved assessments expose a safe create-development-plan action for M4.

## 10 Plans progress notes and reporting work packages

### M4 Approved 30 day development plans

Shared planning target: component of the provisional 2–3-week build/local-acceptance window; see section 6.

Bernie owns duration/calendar/workload changes, generation and approval invariants, scheduling/capacity behavior and technical checks. Saturnino designs plan creation/editor/member views and may implement bounded presentation-layer slices; he reviews their usability and accessibility.

Add an explicit 30-day programme mode alongside supported legacy durations. Proposed interpretation: a 30-calendar-day interval including the start day, with training scheduled only on the member’s working days and a partial final reporting period where needed. Confirm this before M4; four weeks is not silently equivalent to 30 days. Preserve old approved programmes and version the duration mode.

Generate a draft from approved assessment strengths/gaps and target outcomes, with reading, practice, mentoring, projects, assessment and review activities. Show daily/weekly time, milestones, resources and normal workload alongside training allocation. Coordinator edits and approval bind exact inputs. Aggregate concurrent programme capacity and reschedule leave without overwriting started work.

Acceptance: leap/month-boundary and holiday cases schedule correctly; 30-day and legacy plans coexist; capacity is never exceeded; stale/changed inputs invalidate approval; the member sees only the approved plan. Development targets, success criteria and resources have named human owners.

### M5 Weekly progress member notes dashboards and reports

Shared planning target: component of the provisional 2–3-week build/local-acceptance window; see section 6.

Bernie owns weekly/notes domain services, authorised report projections/exports, AI analysis/adaptation wiring, metric correctness, notifications and technical checks. Saturnino designs progress/notes screens, role dashboards and growth graphs and may implement bounded UI slices with accessible table/text alternatives.

- Capture completed/incomplete activities, time spent, results, challenges, comments, evidence and self-assessment in an immutable submitted weekly report with revisions and receipts.

- Compare periods using approved and comparable evidence. AI suggests improvement/stall/missed-target explanations and plan changes; significant changes always require coordinator approval.

- Implement member-to-coordinator notes with an assigned recipient, status, reply/acknowledgement and minimal-link notifications. Reassignment changes access explicitly; notes are not public to a team.

- Build member, coordinator and admin dashboards; baseline-to-follow-up growth charts; scoped weekly/member/team reports and a basic authorised CSV export. Format and additional export types are decided before M5.

Acceptance: approved metrics reconcile with source records; drafts/self-reports are labelled; cross-client/team totals and exports are denied; reassignment cancels stale notifications and export access. Adjustment timing follows the recorded adjustment decision, with concurrency and freeze tests. Existing N+2 behaviour remains until a reviewed policy change is implemented.

## 11 Chatbot and release work packages

### M6 Basic permission scoped training chatbot

Shared planning target: component of the provisional 2–3-week build/local-acceptance window; see section 6.

Bernie owns context retrieval/tool restrictions, conversation lifecycle, AI budgets, scoped history behavior and adversarial/revocation checks. Saturnino designs the chat UI, citations and failure states and may implement bounded presentation slices; his review covers accessibility and clarity.

Answer training questions using the member’s approved plan, goals, published lessons and approved progress. Reuse the shared AI gateway and provider registry. Start with bounded relational retrieval of known authorised records; embeddings and external vector hosting are outside the basic scope unless separately justified and approved.

Keep chat advisory and read-only. It cannot approve results, change plans or permissions, create arbitrary queries, send messages or fetch arbitrary URLs. Link answers to approved source versions, disclose unavailable context, and provide a route to contact the coordinator. Store private conversation ownership, client, member and retention metadata.

Acceptance: the example questions in PDF section 19 return useful, grounded responses for the current member. Peer and cross-client prompts, injected documents and forged citations cannot widen access. Revocation between retrieval and response prevents display. Provider failure gives a recoverable message; rate and cost ceilings hold.

### M7 Integrated qualification representative pilot and release

Shared planning target: component of the provisional 2–3-week build/local-acceptance window; see section 6.

Bernie owns live provider/SMTP qualification, host/migration/restore/security work, integration fixes, technical verification and release preparation. Saturnino supports interface usability/accessibility review, pilot-facing designs, bounded UX fixes and feedback capture. The project owner coordinates authorised participants and accepts milestone/release outcomes.

- Run the complete PDF workflow on staging, including client branding, assessment, approval, 30-day plan, weekly progress, growth graph, notes and chatbot. Use one member consistently for the demo and a second client for access-denial proof.

- Qualify actual AI models and allowed data with representative assessments. Use independent human reviews and adjudication; record costs, disagreement, missing-evidence handling and observed learner/reviewer workload.

- Qualify SMTP sender authentication and delivery evidence, scanner signatures and parser isolation; test real MariaDB/files/keys recovery and safe outbox replay.

- Agree performance/load, retention/erasure, support, monitoring and incident ownership. Rehearse migration, rollback and client suspension. Close critical access, data-loss and approval defects before release.

Acceptance: all applicable release gates have dated evidence and an owner decision. Live services and costs are approved; the project owner signs go/no-go. A demo with mocks may still be delivered while dependencies are blocked, but must be labelled and cannot be called the production-integrated MVP.

## 12 Chatbot and reporting access safeguards

### Enforce access before the model sees data

Build context in a server-side service using the authenticated actor and validated active client. Intersect client, team, member ownership and capability checks before retrieving any text. Never load a whole client database and ask the model to hide records. Treat prompts, uploads, tool results and source documents as untrusted data, not permission instructions.

| Surface | Required isolation and revocation behaviour |
| --- | --- |
| Chat requests and history | Bind conversation and messages to client and member. Guessing another conversation ID returns no data. Coordinators/admins have no default right to private chats; any future supervision access needs a separate explicit policy. |
| Context and citations | Use approved, member-visible source versions only. Exclude raw third-party assessments, private notes, answer keys and provisional results by default. Reauthorise cited objects when opened. |
| Cached or streamed answers | Partition by actor/client/member/permission and content versions. Recheck before generation and before delivery. For MVP, prefer a complete authorised response over streaming that may disclose content after revocation. |
| Reports and charts | Scope source rows before aggregation. Apply the same filters to totals, series, drill-down, labels, search and pagination. No cross-client dashboard shortcuts. |
| Queued exports | Record requesting actor, client, filters and permission version. Recheck during generation and at download; cancel or regenerate if membership changes. Private, expiring delivery and audited access only. |
| Notifications and logs | Recheck actual recipient access at send time. Send minimal links, not assessment details or attachments. Redact prompts, PII and secrets from operational logs; retain only authorised audit metadata. |

### Defence against indirect disclosure

Use bounded, allowlisted retrieval operations and schema-validated model output. No arbitrary SQL, file reads, network tools or permission mutation. If a response references unavailable data, suppress the reference and return a safe limitation. Chart datasets and downloadable files need the same protection as visible UI elements; client-side hiding is insufficient.

### Approval and audit boundaries

Chat advice is not an official assessment, KPI result or plan change. Store model/prompt/configuration version and permitted source references without unnecessary sensitive text. Audit report generation/download and denied sensitive retrieval. Define separate retention for chat, notes, reports and official training history before real data is admitted.

MVP access tests must cover cross-client, same-client wrong-team, peer, reassigned coordinator, suspended client, expired permissions and a revocation racing a job or response. A single data-disclosure failure blocks chatbot/report release until fixed and retested.

## 13 Reporting definitions and acceptance examples

Bernie owns shared, permission-filtered projections, filter semantics and metric correctness. Saturnino designs charts, tables and filters and may implement bounded presentation slices against stable C07 fixtures. Definitions are versioned and approved before dashboard acceptance. Report access never grants additional access to underlying assessments.

| Output | Definition and acceptance rule |
| --- | --- |
| Member growth graph | Show initial approved assessment, approved weekly competency evidence and follow-up assessment by date. Compare only compatible skill/rubric versions and scales. Show a gap or separate series when data is missing or incomparable. |
| Weekly progress | Display planned/completed/incomplete activities, self-reported time, challenges and reviewed results. Label self-report separately from verified competence. Preserve reporting period, timezone and plan version. |
| Completion percentage | Use completed required activities divided by required activities due in the selected period, under a versioned plan. If denominator is zero, show not applicable. Do not infer competence from completion alone. |
| Development scores | Use the approved scoring rubric, not an invented average of unrelated measures. Skill-level results retain source decision and scale. Overall development score is conditional on an approved comparable formula. |
| Coordinator attention queue | Include only assigned members with defined signals such as pending review, overdue work, missed targets or support notes. Agree thresholds; AI suggestions do not independently mark a member unsuccessful. |
| Client and team summaries | Require explicit reporting scope; aggregate authorised records only. Client-level administration cannot expose unassigned personal details. Cross-client operational counts use a separately restricted projection. |
| CSV export | Use the same metric/filter contract as the screen, neutralise spreadsheet-formula cells, minimise personal fields, and store the file privately. Include generation time, timezone, source versions and requester scope. |

### Illustrative acceptance cases

A member has 10 required activities due and six complete: completion is 60%, while competency remains pending until approved evidence exists. A coordinator assigned to only Team A1 sees neither Team A2’s members nor its counts. A report queued before team removal cannot be downloaded after that removal.

A weekly self-assessment of 80% is shown as self-reported and does not overwrite an approved baseline score. A rubric change between week two and week three produces a labelled break or separate comparison, not a misleading growth line. A client with no permitted observations sees no evidence, not zero performance.

### Presentation and usability

Support client/team/member and date filters only within the actor’s scope. Show freshness and last-sync timestamps, pending review and missing evidence clearly. Provide accessible text/table alternatives to graphs, keyboard navigation and responsive layouts. Keep download expiry and report status visible without exposing storage keys or internal errors.

## 14 Verification pilot and release gates

| Gate | Evidence required | Accountable owner |
| --- | --- | --- |
| Foundation | Two clients and multiple teams; allow/deny tests for all record paths, aggregates, files and jobs; migration integrity and revocation evidence. | Bernie; documented technical self-review |
| Functional regression | Assessment approval, 30-day plan, weekly submission, notes, graph and chatbot journeys; stale-version and concurrent reviewer/capacity tests. | Bernie; Saturnino reviews applicable UX/accessibility |
| Live Cerebru | Real sign-in/MFA and permitted delegation, client/team mapping, import/sync, duplicates, deactivation, outages and reconciliation. | A |
| AI qualification | Approved model/data policy and budget; evidence-grounded scoring, independent human comparison, failure/ambiguity handling and no automatic final decisions. | A with human reviewers |
| Usability and accessibility | Responsive role journeys, keyboard and manual assistive-technology checks, readable charts/tables, recoverable uploads/autosave. | Saturnino; Bernie integrates fixes |
| Operations and recovery | Qualified host, private files, scanner/sandbox, secrets, monitoring, encrypted MariaDB/files/keys restore, deletion replay and rollback rehearsal. | A |
| Pilot and go live | Representative user feedback, workload/cost record, open-issue assessment, support ownership, approved recurring costs and signed go/no-go. | Project owner; B records |

### Test layers and evidence discipline

Run fast domain and feature tests per change; run MariaDB integrity/concurrency checks for schema, locking and queues; run browser/accessibility tests for changed user journeys. At milestone boundaries run the integrated suite and record environment, revision, commands, outcomes and known limits. Do not claim production readiness from mocked HTTP, synthetic data, an old CI run or a SQLite restore.

### Pilot design

Propose a small pilot spanning at least two clients and multiple teams, varied roles and accessibility needs. The project owner supplies authorised participants and two independent assessment reviewers; these are business participation dependencies, not additional developers. Use pseudonymous evidence for calibration and agree success thresholds before reviewing model outcomes.

The 2–3-week target covers building and local acceptance only. A representative pilot exercises the assessment-to-learning workflow through a full 30-day cycle, including at least four reporting points, final/partial-period handling and follow-up. Live integration, independent human review, pilot observation and production release are separate M7 gates and may extend the end-to-end schedule; they are not included in the build target. Recruit and approve the cohort and obtain required service access before scheduling those gates.

### Release stop conditions

Do not release with client/team/member leakage, incorrect final-score publication, bypassed human approval, unrecoverable migration/data loss, missing live authentication evidence or unapproved recurring cost. Fixes require targeted and regression retests. Rollout starts with the approved pilot clients, monitored queues and a rehearsed rollback owner; expansion follows the owner’s acceptance decision.

## 15 Dependencies decisions and schedule risks

| Dependency or decision | Owner and needed by | Impact and fallback |
| --- | --- | --- |
| Actual Cerebru architecture/API, identity and sandbox | A obtains from project owner; confirm architecture and sandbox access in Week 1, before M2. | M2 and integrated release block without it. Continue fixtures and independent features; never invent endpoints. |
| Client/grant model, team versus cohort, transfers and multi-client staff | Project owner decides with A; before M1 constraints freeze. | Changes can alter migrations and every policy. Default to explicit single-client membership and no implicit transfer. |
| 30 calendar days versus 30 working days; reporting timezone and partial week | Project owner with coordinator; before M4. | Changes calendar, workload and report denominators. Proposed 30 calendar days is provisional. |
| Next-week adjustment versus existing N+2 freeze | Project owner with A; decision before M4/M5. | Choose reviewed future-only policy. If next-week edits are required, add eligibility/freeze/version rules and re-estimate; no retroactive edits to started work. |
| Rubrics, KPI definitions, success criteria and content/resources | Project owner supplies coordinator/content owner; before M3/M4. | Blocks meaningful scoring/calibration. Synthetic examples may show mechanics only. |
| AI data policy, provider/models and spend cap | Project owner approves A’s request; before first paid call and M7. | Mocks remain available; live quality and cost acceptance remain open. |
| Host, SMTP, retention, erasure, RPO/RTO and support | Project owner approves; A specifies by M2, available before M7. | Operational and live-data gates block. Use isolated staging/sinks until qualified. |
| Pilot participants and independent human review | Project owner arranges; B schedules in Week 1 so observation can overlap implementation. | M7 observation and calibration may extend the schedule. Do not substitute developer fixtures for human acceptance. |

### Key risk controls

Integration discovery is the main external critical path. Tenant retrofitting and historical role assumptions are the main technical risks. Limited weekly overlap is the main coordination risk. Address them with early contracts, small migrations, automated access regressions and a fixed weekly review window. The security and integration owner’s workload is slightly higher, so avoid assigning unrelated work to A during foundation and release windows.

Reforecast when a milestone slips by more than one iteration, the Cerebru contract changes materially, a required paid service is declined, or remaining effort exceeds the reserve. State what is complete, what is mocked, the blocker owner and the next decision date. Do not convert a scope omission into a completed milestone.

## 16 Paid services licensing and cost approval

No purchase, paid API usage, upgrade, recurring subscription or new infrastructure is approved by this document. Existing subscriptions and hosting are not presumed available. The estimates below describe cost drivers; they are not vendor quotes. Obtain current pricing and licence terms when preparing each approval request.

| Potential cost | Driver and approval point |
| --- | --- |
| AI scoring planning analysis and chat | Input/output usage, retries, model choice and chat traffic. Approve provider, region, allowed data, per-client and total monthly caps before enabling live calls. Existing adapters do not imply billing approval. |
| Hosting and staging | Application/worker/database capacity, staging, storage, bandwidth and maintenance. Verify spare existing capacity first; approve any VM, hosting tier or managed service before provisioning. |
| Backups and monitoring | Offsite storage, retention, restore traffic, log volume and alerting. Approve recurring limits and retention; include operational labour in the infrastructure decision. |
| SMTP and sender setup | Monthly volume, dedicated features, domains and delivery monitoring. Use a local mail sink until the sender/service and any plan charge are approved. |
| Cerebru identity or integration licensing | API access, SSO tier, service accounts, user seats or connector fees may apply. Verify existing entitlements during M0–M2; do not assume API/SSO access is free. |
| OCR scanning and document processing | Check deployment licences and support obligations for chosen tools. Managed OCR/antivirus or commercial PDF SDKs are optional alternatives requiring approval. |
| Reporting charts and branding | Review chart/export library licences, fonts and asset rights. Premium charting, BI platforms, custom domains and managed certificates are outside baseline unless approved. |
| Optional search and developer tools | External vector search, embeddings, additional CI minutes/storage and extra developer/AI-tool seats require a business case and approval. Basic chat should first use authorised relational context. |

### Approval packet before commitment

Bernie prepares one request per service: purpose, lower-cost/existing alternative, current vendor quote and date, currency/taxes, one-time setup cost, expected and worst-case monthly usage, hard cap, data handling, licence obligations, renewal/cancellation and accountable owner. Saturnino provides UI-driven usage assumptions when relevant but does not approve technical need or spend. The project owner records approval before credentials are enabled or purchasing occurs.

### Budget measurement

Estimate monthly AI usage from assessment analyses, plan generations, weekly analyses and chat turns, multiplied by measured input/output usage and approved rates, with bounded retries. Add fixed hosting, storage, SMTP and monitoring charges. Track spend by client and task; reserve budget before queue admission and stop at the approved ceiling. No automatic paid upgrade or higher-cost fallback is permitted.

## 17 First actions and reference register

### First-week checkpoint and initial actions

| Developer | Initial deliverables within the shared timeline |
| --- | --- |
| Developer A | Reconcile requirements/code, propose decisions, map client grants and Cerebru/identity dependencies; start owned M1 contracts and foundation tasks when decisions permit. |
| Developer B, UI and Design | Reproduce the UI-relevant synthetic workflow, inventory user journeys and screen states, and prepare M1 client/member designs or bounded UI slices against stable contracts. |

At the end of week one, record how many of the targeted 3–5 work packages met local acceptance, the actual developer hours used, unresolved dependencies, defects and any conflict with other commitments. Reforecast weeks two and three from that evidence; do not infer live or human acceptance.

### Decisions to record without delaying independent work

Record client membership and cross-client staff policy, source-of-truth ownership, the 30-day definition, adjustment timing, report/export format and sensitive-data visibility. Obtain Cerebru contract access, identify human content/review owners and nominate the cost/release approver. Architecture and schema decisions are due before dependent implementation; actual paid services are approved only after a concrete proposal exists.

### Reference register

| Reference | Use in this plan |
| --- | --- |
| docs/Training Assessment Tracker V2.pdf | Primary product scope, 26 pages. Sections 1–25: functionality; 26–31: workflow/governance; 32–34: MVP/demo; 35–37: decisions and product principle. |
| AGENT.md | Existing invariants for access, approvals, workload, AI, integration and release; older single-organisation wording must be reconciled with the PDF. |
| platform/docs/REQUIREMENTS.csv and REMAINING_MILESTONES.md | Local implementation ledger, historical test evidence and remaining live-service gates. Statuses were reviewed, not freshly executed. |
| platform/docs/IDENTITY_AND_DIRECTORY_CONTRACT.md and PILOT_RELEASE_GATES.md | Mock/live separation, delegated access prerequisites and representative pilot obligations. Older pending-work wording is reconciled with the later ledger. |
| platform/app/Services/Access.php, Administration.php and PrimaryPlatform.php | Verified current scope checks, administrative workflow and intentionally unconfigured external adapter. |
| platform/app/Models/User.php and foundation migration | Verified single organisation/role fields, identity keys, groups, memberships, evidence and outbox structure. Basis for additive tenant/grant changes. |

### Document status

This is a proposed implementation baseline, not a production-readiness certificate or approval to incur costs. It reflects the supplied PDF and the reviewed local checkout on 9 October 2026. No application source, existing requirements PDF or historical architecture package is changed by producing this document. Implementation should update the project guidance and traceability map as each milestone is accepted.
