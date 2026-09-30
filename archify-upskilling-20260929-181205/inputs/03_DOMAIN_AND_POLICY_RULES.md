# Domain and policy rules

These implementation rules refine the architecture without changing the user's requested outcomes. Values labelled proposed are configurable defaults, not facts about existing infrastructure.

## 1 Product boundary and user journeys

This is one organisation's internal learning application. Include `organisation_id` and environment on every access-controlled record, but defer commercial multi-tenant billing. Do not introduce separate microservices for each business module. A background worker is a process of the same application, not a separately owned training service.

Admin screens: system health; people and groups; coordinator assignments; local-member promotion; identity configuration; primary API sync; AI provider/model registry; cost budgets; SMTP; retention; audit.
Coordinator screens: assigned groups; review queue; member profile; onboarding wizard; competency map; programme editor and version comparison; submission evidence; grading approval; KPI intervention; adaptation review; absence/calendar management.
Member screens: Today; week plan; lesson reader; approved resources; task drafts; submit and resubmit; receipt; approved feedback; KPI explanation; help/appeal; notification preferences. Meet keyboard navigation, labelled controls, visible focus, sufficient contrast, non-colour-only status and screen-reader feedback requirements. Aim for WCAG 2.2 AA, with verification during implementation.

A lesson must contain usable learning content: objective, explanation, worked example or referenced demonstration, practical activity, permitted tools, estimated time and completion criteria. An outline alone is not a delivered programme. Show answer keys only to authorised graders. Learning resources must be available to the member, within budget and legally usable; do not scrape paid course material.

## 2 Data ownership and member modes

`origin`: primary_import or manual. `environment`: production or test. `sync_policy`: local_only, export_requested or linked. `is_synthetic`: explicit boolean, immutable for an existing training history. Local-only is a sync choice, not a statement that a person is fictitious.

- Production real local-only member: normal learning access and authorised local reports, but no primary-platform retrieval, export or promotion until explicitly permitted.
- Synthetic test member: test environment and credentials, excluded from production dashboards, primary API responses, outbound integration and live SMTP delivery. Test export may go only to an explicitly configured primary-platform sandbox.
- Linked member: immutable source person ID and identity mapping; follow primary field ownership and publish only approved training facts.
- Export requested: pending operation; stay local-only for retrieval until primary acknowledgement and identity verification complete. On partial failure retain the original request key and reconcile; do not generate a new person blindly.

Directory groups and their memberships are primary owned when imported. Locally created training cohorts are application owned and carry a distinct origin; they need not be sent back. Coordinator assignments are local, admin controlled. Never infer coordinator privileges just from membership in a similarly named directory group.

For manual account login, the admin invites/links an identity in the shared identity provider independently of primary-platform sync. An authentik account is not the same object as a primary-platform employee. Do not silently create or link IdP accounts by unverified email alone.

## 3 Authorisation policies

Deny by default. Every protected query requires active identity, active organisation membership, permitted environment, role and record scope. Coordinator access derives from effective assignment to the programme's relevant group and named responsibility. Suspended accounts may not start jobs or download evidence. Queued jobs recheck service/user authority before publishing or sending anything.

Proposed separate permissions: administer_platform, manage_groups, coordinate_training, view_sensitive_assessments, approve_programmes, approve_grades, approve_adaptations, export_member, read_group_reports and manage_integrations. Admin control does not automatically expose raw assessment reports; explicit capability and audit are required. Coordinator self grading and self programme approval need an alternate reviewer.

SSO: authorisation code + PKCE, exact redirect URIs, state/nonce validation, server sessions with Secure/HttpOnly/SameSite cookies, CSRF protection and session rotation on login. Server secrets never enter browser storage. Require MFA evidence according to tested IdP policy, not merely possession of any token. Proposed sessions: 30 minute idle and 8 hour absolute lifetime; reauthentication for secret changes and sensitive exports.

User-question API requests use a training-audience access token with mapped user and caller identity. OAuth scope is necessary but not sufficient. Each list is filtered before pagination and totals. Use 404 for inaccessible object IDs to reduce existence leakage, 401 for invalid token and 403 for an otherwise valid caller without the required operation scope. Machine clients never impersonate a conversational user by supplying an arbitrary user header.

Permission freshness target: five minutes. When a synchronised permission snapshot is older than five minutes, deny privileged and external personal-data retrieval until refreshed; show an actionable stale-access status. Do not claim instant revocation during a network partition. Identity revocation, directory deactivation and local session revocation are all tested paths.

## 4 Calendar and adaptation algorithm

Store event times in UTC; calculate programme dates using the enrolment's IANA timezone and business calendar. Proposed default: Asia/Manila, Monday–Friday, daily minutes selected by the coordinator. The UI must expose and confirm these values at onboarding. A programme week is a dated learning block, not ISO week-of-year.

Allowed durations: 4, 6, 8, 10, 12 blocks. Every scheduled day has 15–120 planned minutes. Required reading, exercises, assessment and rework fit inside that total. Absence removes capacity and triggers explicit rescheduling. Multiple programmes draw from a single member capacity calendar; do not double-book the same minutes.

Generate the complete baseline, approve it, then activate. At any time the current and following block have approved usable content (or all remaining blocks near completion). Members may preview future content but cannot submit against an unfrozen future block; early authorised start immediately locks its version.

For approved evidence from block N:
1. Snapshot the approved grade, KPI observations, target competency, calendar version and policy version.
2. Start with target N+2, within the programme duration.
3. Eligible means not started, no attempt/activity requiring its old content, and current time strictly before its freeze timestamp.
4. If ineligible, move to the next eligible block. Never change N+1 from N evidence. If none exists, propose final support or a follow-on programme.
5. Generate a proposal with old/new version, changed tasks, why, evidence IDs and per-day minute totals.
6. At approval, recheck source grade versions, reviewer access, target eligibility, optimistic version and capacity in one transaction. Stale input => conflict, not silent publication.
7. Publish replacement, preserve old version, notify the member, and emit a revision event. If review is late, retain baseline.

Proposed freeze rule: 17:00 local time on the second business day before block start, using the enrolment calendar. Monday starts normally freeze on the preceding Thursday at 17:00; a holiday shifts the cutoff earlier. This gives Week 2 time to review Week 1 results for Week 3. Readiness is continuous; a previewed upcoming block can be revised before cutoff with a visible change notice.

Absence pause freezes active state, preserves attempts and creates a new future calendar version; coordinator resumes with a preview of shifted dates. Completed work is never rewritten. Emergency correction of broken or unsafe content requires explicit coordinator override, reason and member notice, never silent AI action.

## 5 Content, grading and KPI rules

Programme version states: draft, generating, needs_review, approved, superseded, generation_failed. Enrolment states: onboarding, ready, active, paused, completed, cancelled. A programme template/version and a learner's enrolment are separate entities; do not combine their lifecycle fields.

Submission attempt states: draft, submitted, queued, grading, review_pending, approved, changes_requested, technical_failure, withdrawn. Withdrawal is allowed only before approved review under policy. AI grading attempts have their own immutable states and errors. A regrade is a new grading attempt; a resubmission is a new submission attempt. Approved historical results can only be superseded with a reason, never overwritten or erased.

Rubric criteria have stable IDs, weights summing to 1, score bounds, evidence expectations and competency links. AI output references these exact IDs and evidence supplied to it. Application code computes totals; it rejects hallucinated evidence, out-of-range scores, wrong rubric version or missing required criteria. Objective quizzes use deterministic answer keys. A model's self-reported confidence is not a calibrated probability; use evidence insufficiency flags and human review.

All grades are provisional before review. Only an approved grade creates official KPI observations and adaptation input. Member screens may show “awaiting review” without a score. Appeals assign another reviewer where possible and retain the original decision while the appeal is open.

KPI record: stable ID, version, name, what/why, owner, unit, direction (higher/lower/band), formula, comparable evidence types, baseline and date, target, sample threshold, cadence, business context and data quality. Missing samples yield insufficient_evidence, never a zero. Changing a target creates a new effective-dated definition and must not retrospectively improve past performance.

A competency score is not automatically a workplace result. Final completion requires required tasks, sufficient approved evidence and coordinator confirmation of the target. Time logging is diagnostic, not a proxy for competence. Do not rank different roles on incomparable assessments.

## 6 AI gateway and grounding

Provider registry: provider ID, endpoint allowlist, secret reference, exact model ID, purpose allowlist, schema/file capabilities, limits, privacy/data rules, context budget, input/output unit costs, enabled flag and configuration version. Use official provider adapters; “OpenAI compatible” is not a promise of identical behaviour.

Baseline implementation should include two interchangeable provider adapters and a deterministic mock adapter, with at least one live provider calibrated in the pilot. A third provider can follow the same contract. Per-task policies choose generation, grading, adaptation and optional second opinion. Pin model configuration for a grading batch; never silently compare incompatible model/rubric results. Fallback must be explicitly allowlisted and logged.

Extraction precedes AI use. Quarantine and scan uploads, extract in a restricted process, let the coordinator confirm important scores/interpretation, and send the minimum necessary sections. Treat all uploaded and retrieved content as untrusted. Grading has no execution tools, network retrieval or privileged actions. Any code-assessment sandbox is a later isolated service, not execution on the web VM.

Version prompts, schemas, references and policy. Validate structured responses both syntactically and semantically. Content resources require verified source IDs; citations not found in the resource catalogue are rejected. A refusal or invalid output is a reviewable failure, not permission to fall back to unchecked prose.

Costs are estimated before enqueue, reserved against organisation/programme limits, reconciled after completion and retained on ambiguous upstream timeout. A retried provider call may incur duplicate provider charges; local idempotency does not guarantee provider billing idempotency. Track external request IDs, attempt count, actual tokens, currency, rate version and provider. Do not retry blindly after unknown completion.

## 7 Queue, outbox and file policies

Initial named queues: interactive/extraction, ai_generation, ai_grading, integrations and notifications, with concurrency caps so one programme generation cannot starve submissions. Database queue is sufficient for the pilot. Job payloads contain record references and versions, not entire sensitive documents. Jobs enqueue after transaction commit; business change + outbox entry share one transaction.

Proposed timings: provider HTTP timeout 90 seconds, job timeout 120 seconds, database queue retry_after 180 seconds, three transient retries with jitter. Validate support on the actual PHP and hosting environment; split long generation into bounded per-week jobs with an aggregate status. Job timeout must stay below retry_after. cPanel finite workers must exceed a single allowed job duration; cron overlap locks and scheduler locks use a shared store.

Apply rate limits per caller, actor and provider. External API lists default to 50 records and cap at 100. Persist event IDs and monotonically increasing per-entity versions. Use outbox delivery with at-least-once semantics and consumer deduplication, not a claim of exactly-once networking.

Proposed file limit: 20 MiB each; quota configurable. Allow PDF/DOCX/TXT assessments and PNG/JPEG/PDF/DOCX evidence, verify signature and type, block macros/archives at launch, and recheck authorisation on every private download. OCR failure requests manual correction. Storage object keys are random and never public URLs. Linked evidence URLs must not become an arbitrary server-side URL fetcher.

## 8 Operations and production readiness

Backup proposal remains RPO 24 hours / RTO 4 hours from v1; these are not accepted business guarantees. Pilot owners must decide whether more frequent backups are needed. Test a complete restore including keys, files, database and IdP config. Encrypt backups, keep a separate host copy, and monitor restore success.

Retention proposal: training evidence 24 months after programme closure, audit metadata 24 months, sensitive AI request/response traces no more than 30 days. These are product defaults to be reviewed against the organisation's actual obligations, not legal advice. Support holds and approved erasure; purge caches, exports and provider copies where supported, with an auditable status.

Observability: request/job/event correlation IDs; sync age; queue age; error rates; AI spend; unreviewed grades; missed cutoffs; disk/quota; backup freshness; SMTP bounces. Never log tokens, credentials or entire assessment bodies in ordinary logs.

Use one deployment artifact with environment-specific configuration. Migration gates include backup, compatible schema migration, maintenance if required, worker restart, smoke check and rollback. No production credential is required to finish this design. All untested integrations remain explicitly proposed.
