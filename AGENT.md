# Training Assessment Tracker V2 — implementation guide

## Purpose and current state

Build one organisation's internal upskilling platform: personalised learning, coordinator-approved assessment, evidence-based KPIs, and controlled future programme adaptation.

The initial repository contained the architecture/specification package only. The first local foundation now lives in `platform/`; see `platform/README.md` for setup and `platform/docs/MILESTONE_01.md` for actual verification and outstanding work. Live identity, primary-platform integration and production readiness remain pending. The separate design package records seven delivered native diagrams; D04, D05, D09 and D10 have blocked layouts, and its browser/perceptual checks remain incomplete. Update implementation status only from actual code and execution evidence.

This file records project implementation rules. Reading it does not itself authorise starting the build or running the packaged execution prompts. Follow the user's current task scope. Once implementation is requested, complete locally achievable work without asking for routine per-file approval.

## Authoritative sources

Use `archify-upskilling-20260929-181205/inputs/` as the specification root. Read these before implementing the relevant area:

- [Product architecture](archify-upskilling-20260929-181205/inputs/02_PRODUCT_ARCHITECTURE_SPEC.md): complete product scope and journeys.
- [Domain and policy rules](archify-upskilling-20260929-181205/inputs/03_DOMAIN_AND_POLICY_RULES.md): exact access, calendar, grading, queue and data rules.
- [Decisions and defaults](archify-upskilling-20260929-181205/inputs/10_DECISIONS_AND_DEFAULTS.md): proposed defaults and confirmation boundaries.
- [OpenAPI contract](archify-upskilling-20260929-181205/inputs/contracts/openapi.yaml) and [application schemas](archify-upskilling-20260929-181205/inputs/contracts/application-schemas.json): proposed interfaces and payload shapes.
- [Requirements traceability](archify-upskilling-20260929-181205/inputs/07_REQUIREMENTS_TRACEABILITY.csv), [acceptance scenarios](archify-upskilling-20260929-181205/inputs/08_ACCEPTANCE_TESTS.md), and [delivery backlog](archify-upskilling-20260929-181205/inputs/09_DELIVERY_BACKLOG.md): scope, verification and delivery gates.
- [Implementation handoff](archify-upskilling-20260929-181205/inputs/11_IMPLEMENTATION_HANDOFF_PROMPT.md): build expectations when implementation is requested.
- [Synthetic fixtures](archify-upskilling-20260929-181205/inputs/fixtures/synthetic-scenarios.json): test examples only; repeated sample lessons are not a production curriculum.
- [Design handoff](archify-upskilling-20260929-181205/HANDOFF.md), [semantic review](archify-upskilling-20260929-181205/SEMANTIC_REVIEW.md), and [validation report](archify-upskilling-20260929-181205/VALIDATION_REPORT.md): artifact limitations and evidence.

Explicit user instructions and the written v2 requirements take precedence over older wording, diagrams and examples. Use the domain rules for precise behaviour. Record material ambiguities or contract changes; do not silently relax an invariant. This guide summarises the specification and does not replace it.

The nested `archify-upskilling-20260929-181205/archify-upskilling-20260929-181205/` copy matched the outer package during initial review. Use the outer package as the working reference; preserve supplied artifacts. Paths referring to another machine in historical receipts are provenance, not local commands to execute blindly.

## Architecture and repository practices

The B03 implementation and limits are recorded in `platform/docs/MILESTONE_02.md`. Learning mutations serialize on the member row, retain onboarding/calendar/content revisions, and bind approval to exact input versions. Preserve these rules when extending active learning, AI generation or adaptation.

B04 generation is documented in `platform/docs/MILESTONE_03.md`. Reserve spend under the organisation lock before queue admission. Treat `ai_blocks` as a durable dispatch ledger, recheck permission/input/provider policy before calls, and keep provider I/O outside database transactions. Ambiguous calls never auto-retry or release their reservations. Generated blocks can create only a validated draft; the existing exact-version human approval gate remains mandatory. Provider contract tests use HTTP fakes, not live credentials.

- Use a Laravel modular monolith with Blade and small Livewire components, MariaDB, database-backed queues, locks and sessions, private storage, and a separate worker/scheduler running the same application code.
- Keep domain policies and state transitions in application services/policies, not only UI checks or model prompts. Enforce invariants in every applicable HTTP, job, integration and download path.
- Verify and pin supported PHP/Laravel/dependency versions at build time. Commit dependency lockfiles and document reproducible setup and actual test commands once available.
- Keep long-running generation, extraction, grading, sync and email out of browser requests. Return an authorised job reference and allow polling.
- Prefer a dedicated Proxmox Linux VM for production. cPanel is conditional on demonstrated PHP/CLI, cron, worker-duration, storage, extension and outbound-network capabilities. Do not add a mandatory Redis, Node server, WebSocket, Docker or local identity-provider dependency to the cPanel runtime.
- Preserve unrelated work. Never reset, delete or overwrite existing changes to simplify implementation. Keep secrets out of source, logs, fixtures and browser storage; use placeholder-only `.env.example` files.
- Maintain a requirements-to-code-and-test map as implementation progresses. Distinguish implemented, tested, mocked, pending and blocked work.

## Identity and authorisation

- Assume authentik with OIDC and mandatory MFA pending product/instance confirmation. Use authorisation code + PKCE; validate issuer, audience, signature, expiry, state and nonce. Verify MFA evidence under the actual IdP policy.
- Identify users by immutable issuer + subject, with explicit primary-person mapping. Never link identities by an unverified email/name match.
- Deny by default. Check active identity, organisation, environment, role, effective group assignment and record scope. Include organisation and environment on access-controlled records.
- Members see their own records. Coordinators see only assigned groups and their authorised enrolments. Each enrolment has an accountable coordinator. Self-grade and self-programme approval require an alternate reviewer.
- Administrative control does not automatically grant sensitive-assessment access. Require an explicit capability and audit sensitive access.
- Apply record filtering before pagination, totals and aggregation. Recheck private downloads, queued publication, job results, exports and notification recipients.
- Keep delegated user-question credentials separate from directory/sync machine credentials. A machine token plus an arbitrary user header is never valid delegation. Never expose unrestricted database or evidence access to the primary AI platform.
- Invalidate access on deactivation/removal receipt. The proposed permission freshness limit is five minutes; stale synchronised permissions deny privileged and external personal-data retrieval until refreshed.
- Follow the proposed API semantics: 401 invalid authentication, 403 missing operation scope, 404 inaccessible object, 409 version/idempotency/freeze conflict, and 503 stale permissions or unavailable dependency.
- Use secure server sessions, CSRF protection and session rotation. Proposed limits are 30-minute idle and eight-hour absolute sessions, with reauthentication for sensitive exports and secret changes.

## Data ownership and integration boundaries

- The primary platform owns imported people, employment status and directory groups/memberships. The IdP owns credentials/MFA. Training owns curricula, evidence, grades, approvals and training history.
- Locally created cohorts and coordinator assignments are application-owned. Directory group membership never implicitly grants coordinator authority.
- Keep `origin`, `environment`, `is_synthetic` and `sync_policy` distinct. Do not change synthetic status for an existing training history.
- Real local-only members can learn and appear in authorised local reports. Exclude them from primary-platform retrieval/export until explicitly linked.
- Synthetic records stay in test environments and never enter production reporting, primary API responses, outbound production integration or live SMTP. Use isolated test credentials, databases, endpoints and a mail sink.
- Promotion requires authorised action, verified identity, duplicate review, a stable idempotency key, primary acknowledgement and verified mapping. Keep pending promotions excluded externally. Reconcile ambiguous timeouts using the original key; never blindly create a second person.
- Linking does not automatically export historical training. History export is a separate explicit decision.
- Publish approved, permission-filtered facts with provenance, versions and freshness. Exclude raw assessments, private notes and submission bodies by default. Partition retrieval caches by identity/permission version and recheck access before display.
- Use unique event IDs, per-entity versions, ownership rules, inbound ledgers, tombstones and cursor reconciliation. Persist changes before advancing cursors. Same idempotency key with different payload must conflict.

## Learning content and workload

- Implement the resumable onboarding journey: profile/role, target capability, confirmed assessment extraction, capacity/calendar, KPIs, resources, and generation review.
- Permit exactly 4, 6, 8, 10 or 12 learning blocks, with 15–120 planned minutes per scheduled day. Reading, practice, assessments and expected revision all count.
- Concurrent programmes share one member capacity calendar. Absence reduces capacity and requires explicit rescheduling; never silently increase hours or lower success criteria.
- Store timestamps in UTC and calculate schedules using the enrolment's IANA timezone and versioned business/holiday calendar. Proposed visible onboarding defaults: Asia/Manila and Monday–Friday.
- Deliver usable lessons containing objectives, explanations, worked examples or approved demonstrations, practical activities, permitted tools, time estimates and completion criteria. Topic headings and links alone do not satisfy delivery.
- Verify resource identity, relevance, access, cost and permitted use. Restrict answer keys and private reviewer notes to authorised reviewers.
- Provide accessible responsive screens, keyboard operation, labelled controls, visible focus, screen-reader feedback, recoverable autosave and clear submission receipts. Verify against the specified WCAG 2.2 AA target during implementation.

## Approval, attempts and KPIs

- AI never approves programmes, grades, adaptations, permissions, budgets or KPI targets. Coordinator decisions bind exact versions and preserve actor, time, rationale and evidence.
- Separate programme-version, enrolment, submission-attempt, grading-attempt and adaptation-proposal lifecycles. Implement the states defined in the domain specification.
- Published content and submitted attempts are immutable. Resubmission creates a new submission attempt; regrading creates a new grading attempt. Superseding approved results requires a recorded decision/reason and preserved history.
- Recheck authority and optimistic versions on mutations. Concurrent reviewers or stale inputs must conflict rather than overwrite.
- Mark objective quizzes deterministically. Validate AI criterion IDs, rubric versions, score bounds and actual evidence references; compute weighted totals in application code. Rubric weights sum to one.
- Grades remain provisional until human approval. Members see pending status by default; provisional grades do not update official KPIs, completion, adaptations or the primary approved feed.
- Support appeals and alternate review where possible. Preserve the original decision while an appeal is open. Technical failures and missing evidence never become automatic zero scores.
- Version KPI formulas, targets, baselines, units, direction, rationale, sample requirements and evidence lineage. Distinguish participation, demonstrated competence and workplace outcomes; aggregate only comparable evidence.
- Missing or insufficient samples yield `insufficient_evidence`. Target changes are effective-dated and do not improve historical snapshots retrospectively.
- Completion requires sufficient approved evidence, required work and coordinator confirmation of target capability. Time spent or model confidence alone cannot establish competence.

## Adaptation and calendar invariants

1. Generate and approve baseline content for every programme block before activation. Keep the current and following blocks ready, or all remaining blocks near completion.
2. For approved evidence from block N, start with target N+2, based on the evidence block rather than the current calendar week. Never change N+1 using N evidence.
3. A target is eligible only if in range, unstarted, without activity requiring the old content, and strictly before its freeze cutoff. Early authorised start locks the version immediately; preview alone does not.
4. Default freeze: 17:00 enrolment-local time on the second business day before block start. A normal Monday start freezes Thursday at 17:00; holidays shift the cutoff earlier.
5. Proposals preserve target competencies, prerequisite ordering and the shared daily capacity budget. Include reasons, evidence and source grade/calendar/policy/base versions.
6. At approval, transactionally recheck authority, source versions, target eligibility, optimistic version and capacity. Stale/late proposals cannot publish.
7. If N+2 is ineligible, retain its baseline and consider the next eligible later block. If none remains, propose final support or a follow-on programme.
8. Publish approved replacements as new versions and notify the member. Regraded source evidence marks dependent unstarted proposals stale; completed history remains intact.
9. Pause/leave preserves attempts and versions future calendar changes. Emergency correction of current broken/unsafe content requires an explicit coordinator override, reason and notice.

## AI, files, queues and notifications

- Build two provider-specific adapters plus a deterministic mock behind one application gateway. Support per-task routing for generation, grading, adaptation and KPI suggestions; model switching must work beyond a UI selector.
- Version exact model configuration, prompts, schemas, policies and source references. Record capabilities, permitted data classes, limits, pricing and secret references. Use only explicitly allowed fallback and log provider changes.
- Validate output shape and business semantics, including duration, workload, rubric coverage and resource/evidence IDs. Bound repair attempts; refusals/invalid outputs remain visible failures.
- Reserve estimated spend before enqueue; reconcile usage afterwards. Retain/reconcile ambiguous provider charges. Local idempotency does not guarantee provider billing idempotency.
- Treat uploads, extracted documents, external content and model output as untrusted. Quarantine/scan files, validate signatures/types, restrict extraction, sanitise rendered content and minimise data sent to providers.
- Grading has no execution tools, arbitrary network retrieval or privileged actions. Evidence links must not become unrestricted server-side fetches. Block private-network fetch destinations and redirects.
- Proposed file limit: 20 MiB. Assessments allow PDF/DOCX/TXT; evidence allows PNG/JPEG/PDF/DOCX. Block macros/archives at launch; failed OCR requires manual correction. Use random private object keys and authorised downloads.
- Use separately bounded extraction, generation, grading, integration and notification queues. Payloads contain record references and versions, not whole sensitive documents.
- Persist business changes and outbox entries in one transaction; enqueue after commit. Use at-least-once delivery plus deduplication, never a claim of exactly-once networking.
- Proposed timings: 90-second provider HTTP timeout, 120-second job timeout, 180-second queue `retry_after`, and three transient retries with jitter. Validate host compatibility; keep job timeout below `retry_after` and split long generation into bounded week jobs.
- Send minimal secure review links through generic SMTP, provisionally SMTP2GO. Recheck recipient access at send time, deduplicate notification events, and distinguish relay acceptance from delivery. Email failure must not lose the in-app review queue.

## Delivery sequence and verification

| Increment | Implementation outcome | Main acceptance coverage |
| --- | --- | --- |
| B01 | OIDC/MFA, directory/delegation contracts, deactivation | T01–T03, T13 |
| B02 | Application foundation, policies, identities, private files, audit, queue/outbox | T04–T05, T18, T27, T30 |
| B03 | Onboarding, assessment confirmation, calendars, versioned content editor | T06, T10–T12, T24 |
| B04 | Two AI adapters + mock, routing, budgets, validation and grounding | T15–T16; live calibration later |
| B05 | Lessons, submissions, reviews, appeals/resubmissions and queued SMTP | T17–T19, T21, T24 |
| B06 | Versioned KPIs, evidence lineage and N+2 adaptation | T07–T12, T20, T25 |
| B07 | Promotion, approved feed, scoped retrieval, cache invalidation and tombstones | T01–T05, T13–T14, T19 |
| B08 | Deployment artifacts, monitoring, retention and backup/restore | T22–T23, T28–T30 |
| B09 | Representative pilot, calibration, workload/spend limits and ownership | Agreed operational/effectiveness gates |

Use the backlog's dependencies and gates. Synthetic adapters may unblock local foundation work; they do not complete live B01 integration proof. Do not stop at scaffolding once the implementation stage has been requested and independent local work remains.

- Add meaningful unit tests for authorisation, state/version rules, shared capacity, holidays/timezones, exact freeze boundaries and N+2 selection.
- Add integration tests for transactions, job recovery, outbox delivery, duplicate/out-of-order events, promotion ambiguity, API filtering and local-only/test isolation.
- Add browser tests for coordinator/member journeys, accessible interaction, draft recovery, submission receipts and review conflicts.
- Execute relevant acceptance scenarios and record expected/actual outcomes. Report failed, blocked and not-run checks honestly; mock results are not live integration evidence.
- Keep architecture artifact checks distinct from application security, load, restore and grading calibration. T26 concerns the diagram package.
- Deliver migrations, lockfiles, setup commands, placeholder configuration, deployment/rollback instructions, integration runbooks and role-specific user guides alongside code.

## Production confirmations and scope limits

Local development uses synthetic data, deterministic mocks and non-delivering email until live use is authorised. Do not deploy, push remotely, invite users, send real notifications, access real staff assessments, use production credentials or make paid AI calls without applicable user authorisation. Follow host/tool permission rules.

Before production, confirm identity product/instance and delegation, actual primary API/person mappings, host/network limits, exact AI models and data handling/region, SMTP sender, scale, holiday calendar, named operational owners, retention and recovery obligations.

Proposed targets remain unverified: 100 members/20 concurrent users; ordinary dashboard reads p95 under 500 ms server time; dashboard usable within two seconds on an agreed device/network; RPO 24 hours/RTO four hours; evidence/audit retention 24 months and sensitive AI traces no more than 30 days. Measure and obtain the appropriate business decisions before treating them as guarantees.

Test isolated restore of database, private files, keys and IdP configuration, including safe outbox replay and deletion-ledger reapplication. Monitor queue/sync age, worker health, costs, errors, review backlog, cutoffs, disk, backups and SMTP failures. Never log secrets or full assessment bodies in ordinary logs.

Deferred scope: commercial multi-tenant billing, SCORM/xAPI, native mobile apps, full video hosting, production code-execution sandboxes, plagiarism/AI-authorship scoring, automated employment decisions and fine-tuning. Do not add these to the initial release without an explicit scope change.
