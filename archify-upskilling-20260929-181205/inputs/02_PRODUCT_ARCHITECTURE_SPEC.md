# Internal upskilling product and architecture specification

Version 2.0 • Proposed design • 29 September 2026

Read this with `03_DOMAIN_AND_POLICY_RULES.md`, which supplies exact implementation policies, and `10_DECISIONS_AND_DEFAULTS.md`, which resolves design defaults. This file is the text-first source for the Archify run. It includes the original requirements and expanded design. The accompanying schemas are business contracts, not native Archify IR.

## Internal Upskilling Platform Architecture Plan

A lightweight platform for personalised training and coordinator approved development

Prepared for the platform owner, training coordinators and implementation team. Version 2.0 • 29 September 2026 • Proposed architecture, not a deployed system.

### Recommended direction

Build a Laravel application with server rendered screens, a relational database and background jobs. Run the production platform on a dedicated Proxmox Linux VM. Keep the application portable to cPanel, but qualify that server before treating it as a production option. Use external AI APIs; no local GPU or model hosting is required.

Use a central identity provider for MFA and shared sign in. Treat the primary AI platform as the organisation’s authoritative directory and consolidated information source. The training application owns training evidence and its approval history, and exposes approved, permission filtered information to that primary platform.

### Core operating decisions

- A primary administrator controls the platform. Coordinators can manage only their assigned groups; members see their own training and results.
- Programmes run for 4, 6, 8, 10 or 12 weeks with 15–120 minutes per scheduled training day. The coordinator approves targets, workload, content and grades.
- Generate a complete programme and an approved baseline for every week. Keep two weeks ready; use Week N results to revise Week N+2 while Week N+1 stays stable.
- AI grades are provisional. Approved grades drive official KPIs and adaptations. Members can question feedback and request human review.
- Manual members default to local only. Test members and test data never leave the test boundary or enter production reporting.
### Assumptions that must be verified

The requested “Authentic / getauthentic.io” could not be verified. This plan assumes authentik at goauthentik.io, which documents OIDC and MFA [1–3]. Confirm the product before implementation. “SMTP to go” is interpreted as SMTP2GO, with a generic SMTP adapter retained [6]. Neither service has been configured.

Sizing assumes an initial pilot of up to 100 members and 20 concurrent users. These are planning assumptions, not known headcounts. The existing primary platform API, permission model, cPanel account limits and identity deployment remain to be inspected.

## 1 Application structure and hosting

Use a modular monolith: one deployable application with clear modules for identity, groups, onboarding, programmes, submissions, review, KPIs, integrations and administration. This keeps maintenance and infrastructure small while allowing a worker to scale separately later.

| Layer | Proposed implementation |
| --- | --- |
| Web and domain logic | Laravel on a supported PHP release; Blade and small Livewire components; prebuilt CSS and JavaScript. |
| Database and queues | MariaDB for application records and a durable database queue. Database backed locks and sessions. No Redis requirement at launch. |
| Background work | Separate worker process and scheduler, using the same application code. Jobs handle AI, extraction, sync, reports and email. |
| Files | Private filesystem outside the public web root; authorised download controller. Storage adapter allows object storage later. |
| Integrations | OIDC client, provider specific AI adapters, primary platform REST adapter, SMTP relay and signed event delivery. |

| Criterion | Proxmox VM | cPanel account |
| --- | --- | --- |
| Workers | Continuous workers with process supervision | Host must permit persistent workers or bounded cron jobs |
| AI and extraction | Control over timeouts and utilities | PHP, CLI, process and utility limits vary |
| Identity service | Separate identity VM recommended | Consume external authentik; do not assume it can run here |
| Operations | Team owns OS, backups and patching | Provider controls some limits and services |
| Decision | Recommended for production | Conditional option after capability test |

Proposed starting allocation: training VM with 2 vCPU, 4 GB RAM and 40–60 GB SSD, excluding large media and backup capacity. Use one or two workers initially, with separately bounded AI concurrency. Benchmark before increasing load. Identity capacity is additional: authentik documents a minimum of 2 CPU cores and 2 GB RAM for its Compose deployment [1].

Pin supported dependency versions during implementation. Prefer Nginx, PHP FPM, MariaDB and systemd supervision on the training VM; Docker Compose is optional. Keep the shared identity service separate so training deployments do not interrupt other applications.

## 2 Deployment boundaries and performance

### Network and runtime design

Browsers connect by HTTPS to the training application. Login redirects to the shared identity hostname. Only the web service is publicly reachable; database, worker, private files and administrative interfaces stay on internal networks. The primary platform calls the training API through a private route or narrowly exposed HTTPS API, depending on its location.

The application enqueues long running work and returns a job identifier immediately. The screen polls an authorised status endpoint. A member can continue reading approved lessons and saving work while AI providers are unavailable. Browser requests must never wait for a full programme generation or grading call.

### cPanel qualification gate

- Verify compatible PHP and extensions, matching CLI PHP, Composer build/deploy access, MariaDB transactions, and a document root pointing only to the application public directory.
- Verify per minute cron availability, outbound HTTPS to identity and AI providers, TLS SMTP, private file storage, upload limits, memory limits and database connection limits.
- Preferred: supervised worker with host approval. Fallback: cron starts a short lived queue worker with an overlap lock, finite run time, and bounded job duration [4–5].
- Measure whether the permitted run time can finish provider calls, file extraction and retries. If not, use a VM worker or move the whole application to Proxmox. A cPanel web tier plus remote workers adds complexity and is not the starting recommendation.
- Build assets before deployment. cPanel must not require a Node server, Docker, Redis, WebSockets or a locally hosted identity provider for the application to work.
### Proposed performance and service targets

| Measure | Pilot acceptance target |
| --- | --- |
| Interactive requests | p95 under 500 ms server time for ordinary dashboard reads at 20 concurrent users, excluding external services. |
| Page usability | Initial dashboard usable within 2 seconds on the agreed office network and test device. |
| Job admission | Normal queue delay below 60 seconds on VM; cPanel allowance set after cron testing. |
| Member synchronisation | Events normally applied within 5 minutes; scheduled reconciliation catches missed changes. |
| Recovery | Proposed RPO 24 hours and RTO 4 hours; increase backup frequency if one day of work loss is unacceptable. |

These are acceptance targets, not measured performance. Measure large cohort submissions, provider throttling, storage growth and coordinator review backlog during the pilot. Defer video hosting, vector databases, microservices and full LMS package compatibility until needed.

## 3 Shared login and access control

### Identity model

Configure each internal application as a separate OIDC client of authentik. Use the authorisation code flow with PKCE, state and nonce; validate issuer, audience, expiry and signature. Match a person using the immutable issuer and subject pair, linked to the primary platform person ID. Email changes must not create a new identity or silently merge accounts [2].

Require MFA through the identity provider. Prefer WebAuthn or passkeys with an approved recovery method; allow TOTP where appropriate. Make enrolment mandatory before access, enforce stronger checks for privileged changes, and test recovery and revocation [3]. Application permissions remain in the training application; a shared login never implies access to every platform.

| Role | Allowed scope | Important restriction |
| --- | --- | --- |
| Primary administrator | Platform configuration, users, groups, provider settings, integrations and audit views | Secrets can be replaced but should not be displayed; sensitive assessment access is explicitly granted. |
| Training coordinator | Assigned groups, onboarding, programme editing, grading review and KPI interventions | No unassigned members; no own grade approval when also enrolled. |
| Member | Own programme, resources, submissions, approved results and progress | No other member data or private reviewer notes. |
| Read only reviewer | Explicitly assigned review or reporting scope | Optional role; cannot publish or change results. |
| Integration client | Approved API scopes and records | Cannot grant itself user access or use a global read token for user questions. |

### Group and account lifecycle

Support many groups per coordinator and member, with effective dates and an accountable coordinator per enrolment. A member in two groups has one programme owner and a combined workload budget. Check permissions server side on every API, download, list, export and background result retrieval.

Primary platform suspension disables the local member and invalidates sessions. Group removal takes effect immediately when received; reconcile regularly and flag stale directory data. Target revocation within five minutes, with a maximum freshness policy for privileged access. Identity logout and application sessions require explicit integration and testing; SSO alone does not solve deprovisioning.

Maintain a controlled emergency administrator recovery procedure with strong authentication, restricted network access and an audit alert. Do not provide a routine password based MFA bypass. Local test members can use isolated test identities without creating primary platform people.

## 4 Directory sync and the source of truth

### Define ownership before building two way sync

| Data | Authoritative owner | Training platform behaviour |
| --- | --- | --- |
| Person ID, employment status, organisation and group membership | Primary platform | Import and reconcile; propose corrections rather than silently overwrite. |
| Credentials and MFA devices | Identity provider | Store identity reference only; never synchronise passwords. |
| Training plans, evidence, grades and review history | Training application | Keep authoritative training records; expose and publish approved versions. |
| Consolidated organisation answers | Primary AI platform | Retrieve permitted training facts with provenance and freshness. |
| Local only and test profiles | Training application | Local-only real people remain in local reports; synthetic records stay out of production. Neither exports by default. |

The primary platform remains the organisation’s consolidated source of truth. Training is an owned subdomain with a defined publishing contract, so neither system competes to overwrite the same fields. If the primary platform must store full authoritative training records, agree its versioned write contract before build; do not create uncontrolled dual ownership.

### Manual creation and promotion

Assign every profile a UUID, origin, environment and explicit sync policy: local_only, export_requested or linked. Default manual creation to local_only and make test status prominent. Promotion requires an administrator action, duplicate review and a preview of fields to send. A name or email match is a candidate, not automatic identity proof.

Send a promotion with an idempotency key. Store the returned primary person ID only after acknowledgement; repeat requests must not create duplicates. After linking, primary owned fields follow primary rules. Do not silently backfill historical test assessments, grades or programme content. Export of any real training history must be a separate explicit decision.

### Reliable synchronisation

Use event IDs, entity versions, origin markers and a sync ledger. Apply inbound updates transactionally; discard duplicate and stale events. Write outbound events to an outbox in the same transaction as the business change. Deliver with retries, backoff and a dead letter queue. Reconcile using a cursor plus stable pagination; advance the cursor only after persistence.

A conflict queue handles incompatible versions, possible duplicates and invalid group mappings. Use explicit field ownership rather than last write wins. Deactivation is a tombstone with retention rules, not immediate destruction of learning evidence. Use separate production and test credentials, endpoints and databases; production serializers must also reject test records.

## 5 Secure access from the primary AI platform

Expose a narrow, versioned REST API for authorised questions and machine synchronisation. Do not expose SQL, database credentials or unrestricted document search. The AI model selects a permitted tool; deterministic application code authenticates, authorises and limits the resulting query.

### Separate user questions from machine jobs

For user questions, require a signed, short lived delegated access token identifying the user, calling client, training API audience and scopes. Prefer a tested token exchange or on behalf of flow. Evaluate the intersection of user permissions, client scopes, group membership, record status and environment on every request. An arbitrary user_id header supplied alongside a service token is insufficient.

If delegated tokens are unavailable, a trusted primary backend may issue narrowly signed user assertions under a documented trust contract. The training API must still resolve identity and apply its own permissions. Prove this integration before release; a broad machine credential is not an acceptable fallback for conversational access.

For directory import and approved data publication, use separate client credentials with narrowly scoped permissions. Proposed token lifetime is five minutes; validate signatures and rotating keys. Use TLS, rate limits, bounded pagination, request IDs and redacted access logs. Prefer private networking or mTLS for service traffic where supported.

### Initial API contract

| Endpoint | Purpose and access |
| --- | --- |
| GET /api/v1/me/training | Own approved programme and current progress; delegated user. |
| GET /api/v1/members/{id}/summary | Approved progress, KPI snapshots and freshness; own or assigned scope. |
| GET /api/v1/groups/{id}/progress | Permitted group summary with aggregation and pagination. |
| GET /api/v1/changes?cursor=… | Versioned approved changes; dedicated sync scope. |
| POST /api/v1/integrations/directory/events | Inbound directory events; authenticated integration only. |
| GET /api/v1/jobs/{id} | Job state visible only to its owner or authorised coordinator. |

Responses include record IDs, approval status, programme and rubric versions, evidence references, updated_at and data_as_of. Exclude raw third party assessments, private notes and submissions by default. Additional evidence access needs a distinct scope and record authorisation. Apply authorisation before search and pagination; repeat checks on downloads.

The primary platform must partition any retrieval cache by identity and permission version, expire it quickly, invalidate revoked access and recheck before displaying cached personal data. Signed webhook envelopes include event ID, timestamp, version and payload hash; reject expired signatures and replays. No natural language instruction can widen a scope.

## 6 Onboarding and programme design

### A resumable wizard with clear ownership

| Step | Required information and outcome |
| --- | --- |
| 1 Profile and role | Confirm imported identity, group, coordinator, current role, responsibilities, tools, experience, language and accessibility preferences. |
| 2 Desired capability | Target role or specific skill, business purpose, expected proficiency, practical examples of success and prerequisites. |
| 3 Assessment evidence | Upload third party assessment, provider, date, scoring scale and interpretation. Retain source and version; coordinator confirms extracted findings. |
| 4 Capacity and calendar | Select 4, 6, 8, 10 or 12 weeks; 15–120 minutes per active day; days per week, start date, timezone, leave and workload constraints. |
| 5 Measures of success | Choose 3–5 primary KPIs, baseline, target, rationale, evidence method, cadence and reviewer. Record unknown baselines as unknown. |
| 6 Learning resources | Approved internal material, external links, licences, required software and suitable practical tasks. Flag tools or access that are missing. |
| 7 Confirm and generate | Show total time, realistic outcomes, assessment summary, data sent to AI and coordinator approval checklist. Generate a draft asynchronously. |

Members can supply their profile and availability; the coordinator owns target capability and approves the baseline. Do not infer employment suitability or sensitive personal characteristics from an assessment. Distinguish provider scores, self reports, observed evidence and AI interpretations.

### Workload rules

Weekly capacity equals available scheduled days × selected daily minutes, less approved absence. Include reading, exercises, assessments and revision within that budget. Training on non scheduled days is optional and must not be needed to pass. Example: five days × 30 minutes = 150 minutes per week; an eight week programme has 20 planned hours before leave.

Validate the 15–120 minute daily bound in the UI and backend. Sum concurrent programmes against the same daily cap. When the desired outcome cannot fit, propose a smaller target or a longer allowed duration; never quietly increase hours or lower success criteria. Changes beyond twelve weeks require an explicit new enrolment or extension policy.

### Content requirements

Each week contains objectives, prerequisite skills, daily tasks, estimated minutes, resources, a practical deliverable, an assessment rubric and linked KPIs. Preserve original source references. External links require validation for relevance, availability, cost and access; unverified AI suggestions must be clearly marked and cannot become required materials without review.

## 7 AI generation and provider independence

### Application owned AI gateway

Implement one internal interface for generate_programme, grade_submission, propose_adaptation and suggest_kpi_actions. Build two provider adapters plus a mock; calibrate at least one live provider before the pilot. Extend to other providers as required. The platform administrator selects an approved provider and model by task, with an optional per programme override. Coordinator access to overrides is a separate permission.

Store a model registry with exact model ID, supported capabilities, context limit, permitted data classifications, rate limits and effective pricing. Keep keys in encrypted server side settings or deployment secrets. Do not hardcode “the best” model or assume compatible API payloads. Evaluate candidate models on representative training and grading cases before enabling them.

### Structured generation contract

Programme input includes the approved profile snapshot, skill gaps, target competencies, schedule, KPI definitions and authorised resources. Output must match a versioned schema: objectives, skill links, week/day structure, minutes, resource references, assignments and rubric criteria with weights. Provider schema support assists formatting; application validation still enforces meaning and policy [7–8].

The validator checks duration, day budgets, prerequisite order, rubric weights, assessment coverage and source IDs. Reject malformed or infeasible output, request a bounded repair, then surface a failure for human resolution. Never publish unchecked model text or allow an AI response to change permissions, targets or budgets.

### Review and versioning

The coordinator reviews a rendered programme and edits sections directly or provides revision notes to AI. Show a before and after comparison. Approval records the exact immutable version, reviewer and time. Later edits create a new version; tasks with member activity retain their original content and rubric.

Coordinator feedback applies to the relevant draft or regrading attempt. It does not silently retrain the provider or alter organisation wide policy. Promoting a lesson from feedback into shared guidance requires an administrator reviewed policy version.

### Resilience and cost control

- Record provider, model, prompt template version, input references, output version, tokens, latency, cost estimate and validation result per run. Restrict access to sensitive traces.
- Set organisation and programme spending caps, output limits and concurrency. Retry transient faults with jitter; do not retry invalid credentials or policy refusals indefinitely.
- Fail over only to a preapproved provider with equivalent data handling and capability. Record the switch, and keep grades provisional; consistency matters more than silent availability.
- Use a second model only for flagged or sampled cases. Model agreement is not proof. Keep queued work and approved content usable if all providers fail.
## 8 Learning delivery and grading review

### Member experience

Provide a responsive Today screen, weekly plan, accessible lesson reader, resource links, autosaved draft responses, uploads, submission receipt and progress view. Support text responses, short quizzes, document uploads and practical evidence links. Avoid a mandatory native app. Display due dates in the member’s timezone and store timestamps in UTC.

Submitted attempts are immutable. A resubmission creates a new attempt linked to the previous one. Record the exact assignment and rubric version, submission timestamp and evidence hashes. On failed upload or network interruption, preserve the draft and show whether submission actually succeeded.

### Explicit assessment states

Draft → submitted → queued → AI provisional grade → coordinator review → approved or changes requested. A requested AI regrade creates a new grading attempt and returns to coordinator review. A member resubmission creates a new submission attempt. Technical failures remain visible and retryable; they do not become a zero score.

Use deterministic marking for objective questions wherever possible. For practical work, AI returns criterion scores, evidence references, strengths, gaps, suggested feedback and uncertainty flags against the approved rubric. Verify arithmetic and evidence references in application code. Do not permit the model to invent unseen evidence or change the rubric.

The coordinator can approve, override with a recorded reason, request an AI regrade with feedback, or request more evidence from the member. Use optimistic version checks to prevent two reviewers from overwriting each other. Only the current approved grade changes official KPI snapshots, completion and downstream adaptation inputs.

### Email and review workload

Use a generic SMTP transport configured for SMTP2GO, verified sender domain and TLS [6]. Queue notification after a provisional grade is saved. Send the assigned coordinator a minimal submission reference and secure review link; omit scores, assessments and sensitive attachments from email. Recheck recipient group access at send time.

Record notification event IDs to suppress duplicates, track relay acceptance separately from delivery, and surface bounces or failures. Add optional daily digests, member reminders and overdue review escalation. Members see an “awaiting review” state; provisional feedback is hidden by default until approved.

### Fairness and completion

Give members a clear appeal and human review path. Do not use unreliable AI authorship detection as a pass/fail criterion. Publish allowed AI assistance rules per assignment and use practical demonstrations where independent capability matters. Completion requires the coordinator’s final judgement against target competencies, not merely logged minutes or an AI score.

## 9 Rolling adaptation with two weeks ready

Generate the whole programme at onboarding and approve a baseline for every week before activation. Weeks 1 and 2 are ready immediately. Later weeks retain approved baseline content and may receive replacement proposals. This makes the two week buffer resilient even when a review or AI job is late.

| Evidence received | Protected work | Normal adaptation target |
| --- | --- | --- |
| Week 1 approved result | Week 1 history and Week 2 plan | Week 3 |
| Week 2 approved result | Week 2 history and Week 3 plan | Week 4 |
| Week N approved result | Week N history and Week N+1 plan | Week N+2 or next eligible unlocked week |
| Final two weeks | Current work and remaining approved plan | Final review, optional support or next programme |

### Deterministic scheduling rule

For evidence from Week N, propose Week N+2. The target must be in the programme, not started, before its freeze cutoff and have no member activity. Recheck at approval time. Week N+1 is not changed using Week N evidence. If the target is locked, keep its baseline and propose the earliest later eligible week.

Readiness and freezing are separate: two approved weeks remain available, but an unstarted future week can receive a reviewed replacement before its cutoff. Proposed cutoff: two business days before that week starts. For Monday starts, review Week 1 results during Week 2 and approve Week 3 changes by Thursday. Notify the member of the revision. If review misses the cutoff, the baseline stays in force.

### Adaptation logic and controls

Inputs are approved criterion scores, demonstrated competency gaps, approved KPI trends, completion, reported difficulty, actual availability and coordinator notes. A policy engine decides what is eligible to change; AI proposes the exercises, examples and explanations. For example, below an agreed mastery threshold, replace a future stretch exercise with targeted practice and a reassessment using the same skill rubric.

Suggested starting rule: below 70% on a target skill prompts remediation; 70–84% prompts consolidation; at least 85% with corroborating practical evidence permits a stretch task. These are configurable pilot defaults, not universal standards. Require sufficient evidence and avoid reacting to a single anomalous attempt.

Preserve target competencies and KPI definitions; remain within the daily budget and prerequisite sequence. Show why each change was proposed, the evidence used and the time tradeoff. Coordinator approval creates a new future week version. Regrading evidence marks dependent unstarted proposals stale; it does not rewrite completed history.

### Exceptions

An urgent correction to unsafe, broken or unusable current content requires a logged coordinator override, member notification and preserved history. Leave pauses and rebases future dates. Missing work produces “insufficient evidence”, not automatic failure or harder training. Within a four week programme, Weeks 1 and 2 can inform Weeks 3 and 4; later evidence informs closure or a follow on plan.

## 10 KPIs and useful interventions

Every KPI needs a business rationale, owner, baseline date, formula, unit, target, evidence source, measurement cadence, minimum sample and programme version. Keep learning participation, demonstrated competence and workplace outcomes separate. Show missing evidence and overdue review distinctly from a measured shortfall.

| KPI example | Measurement and reason | Example intervention |
| --- | --- | --- |
| Demonstrated skill | Approved weighted rubric score for a named competency; measures ability to perform the target work. | Replace a future stretch task with practice on the weakest criterion. |
| On time completion | Required items submitted by adjusted due date ÷ required items due; exclude approved leave. Measures consistency. | Split a task into shorter sessions within the existing daily budget. |
| First attempt quality | Approved first attempt score or defect rate across comparable tasks; measures reduced rework. | Add a checklist and a worked example for recurring errors. |
| Workplace transfer | Coordinator verified application of the skill in real work against an agreed rubric. Measures practical value. | Schedule a supervised work task with evidence and reviewer sign off. |
| Time fit | Reported task minutes compared with planned minutes; identifies unrealistic workload estimates. | Reduce scope or change resource format rather than adding hours. |

### Scoring and status

An assignment score can equal the sum of criterion score percentages × criterion weights, with weights totalling 1. A competency snapshot aggregates comparable approved evidence under a versioned rule. Do not average unrelated quiz and practical scores without an agreed mapping. Normalise third party scales explicitly and retain the originals.

Show target met, on track, at risk, below target or insufficient evidence, together with the rule and sample count. Example: a target of 80% is met only when the required evidence count and practical gate are satisfied. All thresholds are coordinator approved; no invented “industry benchmark” is required.

### Dashboard and suggestions

Members see the current value, trend, target, reason for measurement and one to three feasible next actions. Suggestions for the current week are advisory and use remaining allocated time; changing required tasks follows the protected week override process. Future plan changes use the adaptation workflow.

Coordinators see their groups, review queue, members needing help, KPI trends, workload exceptions and progress against each target role. Administrators see queue health, sync freshness, usage costs and programme outcomes. Export approved snapshots with calculation versions and evidence references. Avoid public individual rankings and misleading comparisons across different roles or assessment difficulty.

## 11 Data model and auditable records

| Record family | Key fields and relationships |
| --- | --- |
| People and access | members UUID, external_person_id, origin, environment, sync_policy, active; identities issuer+subject unique; groups, memberships and coordinator assignments. |
| Onboarding | onboarding_versions, target_role, competencies, capacity_calendar; assessment_documents with storage key, hash, classification and confirmed extraction version. |
| Programme | programmes and enrolments; programme_versions; week_versions, daily_tasks and resource_references; approval records point to exact versions. |
| Assessment | assignments, rubric_versions and criteria; submissions and attempt numbers; submission_files; grading_attempts; approval and override decisions. |
| Measurement | kpi_definitions and versions, baselines, targets, observations, approved snapshots; evidence links and calculation versions. |
| Adaptation | adaptation_proposals with evidence IDs, target week, input versions, proposed diff, state, reviewer and applied version. |
| Operations | ai_runs, notification_outbox, integration_outbox, inbound_event_ledger, sync_cursors, audit_events and failed_jobs. |

### Integrity and concurrency

Use foreign keys, transactions and UUID identifiers. Uniquely constrain external person IDs per source, identity issuer+subject, inbound event IDs, client+idempotency key and submission attempt numbers. Index group membership, member enrolment, due dates, queue availability, approval state and change sequence.

A published programme version is immutable. Approval references the exact draft version and evidence set; edits invalidate pending approval. Use row locks or optimistic version fields for promotion, review and publication. An AI job key includes task type, entity ID, input version, rubric or prompt version and model configuration; retries must not create duplicate active results.

### Recommended event vocabulary

member.linked, member.deactivated, programme.approved, submission.received, grade.provisional, grade.approved, adaptation.proposed, week.revised and kpi.snapshot.approved. Internal events can carry restricted references; outbound events publish only contract approved fields and environments. A provisional event may trigger review email but must not appear as an official result in the primary platform.

### Traceability

Audit actor, service client, action, target, reason, before and after versions, request ID and timestamp. Protect audit records from ordinary administrator edits and forward security relevant events to separate storage. Avoid embedding full assessment text or secrets in generic logs. Retention and deletion jobs must cover database rows, private files, AI traces, exports and cached copies.

## 12 Security operations and failure handling

### Protect evidence and AI boundaries

Allowlist upload formats and sizes, verify file signatures, scan for malware, and quarantine until safe. Extract in a constrained process; reject active content. Proposed launch scope is PDF, DOCX and plain text assessments, with PNG/JPEG evidence. Scanned files require a validated OCR path or manual entry. Never execute uploaded scripts or macros.

Treat assessment documents, member submissions, external pages and model outputs as untrusted data. Delimit them from instructions; deny tool execution during grading. Sanitise rendered content. A malicious submission must not access other members, secrets, unrestricted URLs or administrative actions. Resource fetchers block private network destinations and redirects to them.

Send only the minimum relevant assessment excerpts to approved AI providers. Define permitted data classes, retention, region and provider handling before using real staff data. Redact personal details when unnecessary. Keep private reviewer notes out of member and primary AI responses unless specifically authorised.

### Continuity and monitoring

| Failure | Required behaviour |
| --- | --- |
| AI timeout or rate limit | Keep approved lessons available; queue retry with bounded backoff; show pending status and alert on prolonged failures. |
| Primary platform unavailable | Retain local records and outbox; mark freshness; restrict sensitive queries when permissions exceed their allowed age. |
| Identity service unavailable | No new login or privilege elevation; existing sessions follow a documented bounded lifetime; use controlled recovery procedure. |
| Email failure | Retain in app review queue; retry and alert. A notification failure must not lose a grade. |
| Review or adaptation late | Retain approved baseline, escalate review and target the next eligible unlocked week. |
| VM or database failure | Restore tested backups and replay safe events; verify files, secrets and database consistency before reopening. |

Monitor web errors, p95 latency, worker heartbeat, queue age, failed jobs, provider spend, disk use, sync lag, stale approvals and email bounces. Keep encrypted backups of database, private files, application secrets and identity configuration off the VM and preferably off the host. VM snapshots alone are not a complete backup strategy.

Test restore before launch and on a proposed quarterly cadence. Use staging with synthetic people, separate API keys and non delivering email. Deploy through versioned releases with database migration checks, worker restart and rollback instructions. Assign named owners for identity, application operations, training quality and incident response.

## 13 Delivery plan and acceptance gates

Deliver in usable increments. This sequence is a scope plan, not a calendar estimate; team capacity, primary API readiness and the identity decision must be known before committing dates.

| Phase | Outcome | Exit gate |
| --- | --- | --- |
| 1 Integration proof | Verify identity product, hosting capabilities, member IDs, group ownership and delegated API access. | MFA login works; cross group access denied; sample directory sync and permitted query succeed. |
| 2 Learning foundation | Groups, manual members, wizard, evidence upload, programme editor and private files. | Realistic synthetic learner completes onboarding; local only and test isolation proven. |
| 3 AI assisted training | Provider adapter, programme generation, validation, approval, learner delivery and submissions. | Approved programme fits time budget and resources; provider outage does not block reading. |
| 4 Review and adaptation | Provisional grading, coordinator review, SMTP, KPI snapshots and rolling revisions. | Week 1 changes Week 3 only after approval; late review preserves baseline. |
| 5 Production pilot | Primary platform publishing, dashboards, reconciliation, hardening and operational handoff. | Restore, load, permission, cost and grading calibration checks pass. |

### Required end to end verification

- Access: members cannot read peers; coordinators cannot access unassigned groups; forged delegation and wrong token audiences fail; revoked access cannot reuse cached personal results.
- Sync: duplicate and out of order events are harmless; timeout after promotion does not duplicate a person; deactivation revokes access; test data never exports.
- Training: all five durations and both daily time boundaries validate; leave and overlapping enrolments preserve capacity; invalid AI output cannot publish.
- Review: resubmission retains history; regrading cannot overwrite an approved attempt; simultaneous reviewers detect conflicts; email retries do not create repeated application events.
- Adaptation: test late evidence, missed cutoffs, changed current week, reassessment, paused enrolment and final weeks. No protected task changes silently.
- Operations: load representative uploads and cohort submissions; simulate provider and SMTP failure; restore database plus evidence; confirm audit links and source platform freshness.
### Pilot quality gate

Use a small representative cohort and coordinator scored benchmark submissions. Compare AI grading with human judgements by criterion, investigate material disagreement and tune the rubric before scaling. Set acceptable disagreement, review workload and spend limits before the pilot. Passing automated tests does not establish learning effectiveness.

## 14 Implementation decisions and references

### Decisions to close before production

| Decision | Recommended starting position |
| --- | --- |
| Identity product | Confirm authentik at goauthentik.io and whether an existing shared deployment is available. |
| Hosting and scale | Proxmox VM; confirm member count, concurrent users, available RAM, file volume and cPanel restrictions. |
| Primary platform contract | Obtain API schema, delegated identity design, permission rules, person IDs, group ownership, endpoints and sandbox. |
| Learning policy | Five training days as a configurable default; coordinator sets duration, daily minutes, target role, KPI targets and cutoff. |
| Privacy and retention | Approve data classes, assessment visibility, AI providers and deletion schedule; no raw assessment export by default. |
| Service ownership | Name primary admin, backup operator, coordinator cover and recovery owners; accept or revise RPO/RTO. |
| Budget and notifications | Set AI spend caps, approved models, SMTP2GO sender and recipient policy, digests and escalation times. |

### Primary technical references

Sources checked on 29 September 2026. These support platform capabilities; VM sizing, timing targets, permission design and delivery phases are design recommendations, not vendor guarantees. Recheck version specific requirements during implementation.

[1] authentik Docker Compose installation
https://docs.goauthentik.io/install-config/install/docker-compose/

[2] authentik OAuth 2 and OpenID Connect provider
https://docs.goauthentik.io/add-secure-apps/providers/oauth2/

[3] authentik MFA authenticator validation stage
https://version-2026-5.goauthentik.io/add-secure-apps/flows-stages/stages/authenticator_validate/

[4] Laravel queues
https://laravel.com/framework/docs/13.x/queues

[5] cPanel cron jobs
https://docs.cpanel.net/cpanel/advanced/cron-jobs/

[6] SMTP2GO SMTP relay
https://developers.smtp2go.com/docs/smtp-relay

[7] Claude structured outputs
https://platform.claude.com/docs/en/build-with-claude/structured-outputs

[8] Gemini structured output
https://ai.google.dev/gemini-api/docs/structured-output

## 15 Archify ready design package

Version 2 expands this plan into a complete input package for an agent using Archify. The package contains an autopilot prompt, a full Markdown specification, precise domain policies, proposed OpenAPI and JSON schemas, synthetic fixtures, eleven diagram briefs, a requirement matrix, acceptance scenarios and an implementation backlog.

### Purpose and completion boundary

The requested outcome is a complete proposed solution design with interactive architecture views. Archify generates diagram artifacts from an agent’s instructions; it does not build or deploy this training application. The separate implementation prompt can be used later to begin the software build. This package does not claim that the eleven diagrams or the application have already been generated.

### Run without local approval pauses

Extract the package into the destination agent’s project workspace and run 01_AUTOPILOT_PROMPT.md. The prompt explicitly authorises creating and revising local source files, diagrams, documentation, validation receipts and archives. The agent should choose reversible defaults, record assumptions, finish all independent work and avoid asking whether to create the next file.

That local authorisation preserves the product’s human approval gates for plans, grades and adaptations. It does not authorise publishing, deployment, remote pushes, real staff data use or live notifications. Host system and tool permissions still apply; the prompt cannot override them.

### Repository compatibility

Reviewed repository: github.com/tt-a1i/archify at commit 69cf672087289033af5138648d3875d3d73fc431 on 29 September 2026. Its README advertises v3.0.1, while the reviewed skill specifies the finalize workflow. Follow the installed skill’s actual schema and CLI and record the revision used; do not assume every published example matches every installation.

The application JSON schemas in the package describe training payloads. They are not native Archify diagram inputs. The agent must author a new candidate for each supported diagram type, then run the documented validation and delivery workflow. Do not invent code source evidence for an application that has not been implemented.

### Expected destination output

An index linking eleven standalone HTML diagrams; native JSON for every view; validation and browser receipts; a requirement coverage matrix; decisions, handoff and manifest files; and a ZIP of the generated suite. Checks must report passed, failed or not run honestly. Automated validation and actual visual inspection are separate statuses.

## 16 Precise member and integration policies

### Local only and synthetic are different

A genuine locally created member can learn and appear in authorised local reports without being exported. Synthetic test members stay in a separate test environment and must never appear in production reporting, production API responses or live SMTP. Identity creation in authentik is separate from creating an employee in the primary platform.

Use origin, environment, is_synthetic and sync_policy as distinct fields. A promotion stays pending until the primary platform acknowledges the idempotent request and the identity mapping is verified. It does not silently export historical training evidence. Imported directory groups follow primary ownership; locally created training cohorts and coordinator assignments remain application owned.

### Concrete API and record contracts

The package includes a proposed OpenAPI contract for delegated member/group summaries, a dedicated approved change feed, directory events, job status, submissions, programme review, grade review, adaptation review and manual-member promotion. Placeholder example.invalid URLs are deliberate; they are not discovered production endpoints.

Every list applies record permissions before pagination and totals. Token audience, expiry and signatures are checked alongside current user/group policy. Directory clients cannot query as a conversational user. Proposed permission freshness is five minutes; if synchronised access becomes stale, deny sensitive external and privileged reads until refreshed.

### Versioned payload rules

| Payload | Required invariant |
| --- | --- |
| Programme draft | Exact duration, scheduled days, usable lesson content, task minutes, resources and approved rubric definitions. |
| Provisional grade | Exact submission/rubric IDs and criterion evidence; the application computes totals and rejects invented evidence. |
| Adaptation proposal | Approved evidence IDs, target week, calendar/policy/base versions, replacement tasks and explicit review state. |
| Integration event | Unique event ID, source, environment, entity version and timestamp; deduplicate and reject stale versions. |

Schema validation establishes shape, not business correctness. Additional checks enforce week counts, time budgets, rubric weights, resource permissions, evidence identity and target eligibility. The contract does not exhaustively describe every administrative UI endpoint; implement those under the same permission and version rules.

## 17 Scheduling and state invariants

### Two weeks ready with a practical review window

Approve baseline content for the whole programme. For Week N results, propose Week N+2. During Week N+1, the coordinator has time to review the grade and adaptation. The target freezes at 17:00 local time on the second business day before its start, or immediately when work begins. For a normal Monday start, the cutoff is Thursday at 17:00.

Use the enrolment’s IANA timezone and holiday calendar. Check target eligibility again when approval is committed. A late or stale proposal keeps the old baseline and moves to the next eligible future week. Do not use current week plus two as an automatic substitute: that would often move Week 1 evidence to Week 4 and lose the intended benefit.

Future content can be previewed before freezing, with a visible revision notice on replacement. Starting work early locks that version. Current attempts and completed history never change silently. Leave creates a versioned calendar adjustment and cannot quietly increase the daily budget.

### Separate the lifecycle records

| Record | State model |
| --- | --- |
| Programme version | draft → generating → needs_review → approved; later superseded. Failed generation is explicit. |
| Enrolment | onboarding → ready → active; paused/resumed; completed or cancelled. |
| Submission attempt | draft → submitted → queued → grading → review_pending → approved or changes_requested; failures retryable. |
| Grading attempt | Separate immutable attempt per AI or human regrade; approved decision can be superseded with a reason. |
| Adaptation proposal | needs_review → approved and applied, rejected or stale; application rechecks freeze and source versions. |

### Workload and KPI consistency

A member’s concurrent programmes share one daily capacity calendar. Assessment and expected revision time count toward the same 15–120 minute limit. Every KPI has a versioned target and formula, sufficient comparable evidence and a rationale. Missing data is insufficient evidence, not a zero score. Changing a target does not rewrite previous snapshots.

A four-week programme can adapt Week 3 from Week 1 and Week 4 from Week 2. Evidence from the last two weeks supports final review or a follow-on plan. Programme completion needs coordinator confirmation of demonstrated capability, not simply accumulated time or AI confidence.

## 18 Content quality and reliable AI operations

### Deliver training content that members can use

Each lesson includes a learning objective, explanation, worked example or approved demonstration, practical activity, permitted tools, estimated minutes and completion criteria. The programme cannot be marked delivered if it only contains topic headings and external links. Verify resource access, cost and licensing before requiring it.

Allow coordinator editing and AI revision notes at section level. Maintain member drafts, immutable submissions, resubmissions and an appeal route. Ensure keyboard and screen reader use, clear focus, mobile layouts and recoverable autosave. Keep answer keys and private reviewer notes behind separate permissions.

### Multiple models with consistent controls

Implement two provider adapters and a deterministic mock behind one interface. Exact models are selected from a versioned registry for generation, grading and adaptation. At least one live model needs calibration against coordinator-scored examples. A provider switch records the change and never promotes a provisional result to approved.

Provider configuration records capabilities, authorised data classes, limits, pricing and secret references. Reserve estimated job cost against budgets before enqueue and reconcile actual usage afterwards. An ambiguous provider timeout can still incur charges; local idempotency does not guarantee idempotent provider billing.

### Queue and integration reliability

Use separate queue names for extraction, generation, grading, integrations and notifications, with bounded concurrency. Job payloads carry record references and versions. Persist the business change and outbox in one database transaction, then deliver with at-least-once semantics and consumer deduplication.

Proposed starting timings: 90 second provider call timeout, 120 second job timeout and 180 second retry_after, with three transient retries. Split long programme generation into bounded week jobs. Confirm these values fit the actual host; a cPanel worker must be allowed to finish a whole job and must not overlap an existing worker lease.

### Operational proposals that still need acceptance

Initial file cap: 20 MiB; reject unsafe types and quarantine until scanned. Evidence/audit retention: proposed 24 months; sensitive AI traces: proposed 30 days. Keep the inherited RPO 24 hours / RTO 4 hours as a proposal until business owners accept it or require more frequent backups. These are design defaults, not legal conclusions or measured guarantees.

The accompanying acceptance scenarios cover deactivation, stale permissions, malicious evidence, provider outages, duplicate promotions, late reviews, holiday cutoffs, backup restore, performance and grading calibration. They are specifications to execute during implementation, not tests of an existing application.

## 19 Diagram coverage and delivery gates

| ID | Type | View |
| --- | --- | --- |
| D01 | Architecture | System context and application components |
| D02 | Architecture | Recommended Proxmox deployment and trust boundaries |
| D03 | Architecture | Conditional cPanel deployment and qualification gates |
| D04 | Workflow | Onboarding, generation and programme approval |
| D05 | Workflow | Submission, provisional grading and coordinator review |
| D06 | Sequence | Permission scoped query from the primary AI platform |
| D07 | Dataflow | Member synchronisation and approved fact publication |
| D08 | Lifecycle | Programme enrolment, pause and completion |
| D09 | Lifecycle | Submission attempts and review outcomes |
| D10 | Workflow | Week N+2 adaptation, freeze and baseline fallback |
| D11 | Dataflow | KPI evidence, calculation and improvement suggestions |

### Verification at the destination

The master prompt requires native JSON, standalone HTML and receipts for all eleven views. It uses the installed Archify schema and finalize workflow, follows its repair limits and preserves last good artifacts. Failed or unavailable checks remain explicitly failed or not run; no hand-edited receipt can turn them into a pass.

The agent then reconciles every requirement with the generated views and acceptance scenarios. No diagram may let AI approve its own work, expose the database to the primary platform, automatically export local-only members, or confuse a ready future week with a permanently frozen one.

### Implementation completion gate

Once the design is reviewed, the separate implementation prompt can build the application in increments: identity/integration proof, secure foundation, onboarding/content, AI adapters, learner/review flow, KPI/adaptation, primary integration, operations and pilot. Use mocks while real API details are unavailable and report them honestly.

This updated document and its machine-readable companion files form the design handoff. A successful diagram build is evidence about the visual artifacts; production readiness additionally requires implemented code, access tests, restore evidence and coordinator calibration of AI grading.

