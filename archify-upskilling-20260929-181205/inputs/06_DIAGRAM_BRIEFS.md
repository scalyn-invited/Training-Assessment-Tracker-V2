# Required Archify diagram suite

All views describe proposed architecture, not live infrastructure. Reuse semantic IDs where concepts recur, but each file must be independently valid under the installed mode schema. Do not fabricate an unsupported ERD mode; the entity catalogue belongs in the written handoff and cards.

## D01 System context and components — architecture

Show member/coordinator/admin browser, shared authentik, training web app, background worker/scheduler, application database/queue, private evidence store, primary AI platform, frontier AI provider gateway and SMTP relay. The gateway is an application module; the providers are external. Group trust boundaries clearly. Distinguish HTTPS browser actions, OIDC login, queued jobs, private reads/writes, delegated API reads, directory sync, model calls and email. Put module responsibilities in detail cards. Do not draw the primary platform directly connected to the training database.

## D02 Recommended Proxmox deployment — architecture

Proxmox host boundary; training Linux VM with reverse proxy/PHP web, worker and scheduler, MariaDB and private evidence; separate shared identity service/VM with its own vendor-managed data services; off-host backup destination; external AI and SMTP; primary platform whose physical location is unknown. Only HTTPS ingress; no public database/file service. Use proposed roles for operational owners rather than invented names/IPs. Label 2 vCPU/4 GB/40–60 GB training allocation as a starting estimate and identity capacity separately. Do not imply high availability from one VM.

## D03 Conditional cPanel deployment — architecture

cPanel account boundary with public document root, private application/config/storage, MariaDB and bounded CLI queue worker/scheduler triggered by cron. Authentik remains external. Show outbound HTTPS/SMTP and primary API. Cards list capability gates: PHP/CLI/extensions, worker run time, per-minute cron, locks, private storage, quotas and outbound access. Explicitly label conditional/not qualified. Do not invent Docker, Redis or root access. Show whole-app move to Proxmox as the decision if gates fail, not an already implemented hybrid.

## D04 Member onboarding and programme approval — workflow

Lanes or equivalent: admin/integration, coordinator, member, application, AI. Branch imported versus manual person; local-only versus requested promotion; identity invite/link separately. Wizard profile/goal/assessment/capacity/KPI/resources -> confirm extraction -> generate draft -> validate -> coordinator edit/AI revision loop -> approve exact version -> activate baseline -> member delivery. Include invalid document, infeasible workload, generation failure, duplicate promotion and missing evidence paths. These should return to correction, not disappear.

## D05 Submission and grading review — workflow

Member draft/autosave -> submit immutable attempt -> queue -> deterministic quiz or AI provisional grading -> schema/evidence validation -> review_pending -> coordinator approve / override with reason / ask AI regrade / request member resubmission. On approval update KPIs, enqueue adaptation and publish approved event. Notify coordinator via SMTP after provisional result saved. Show technical failure and email failure without losing submission. Member appeal preserves original decision. Grading/review loop must never allow AI to approve itself.

## D06 Permission scoped primary AI query — sequence

Participants: authorised user, primary platform backend, identity service, training API, training data/policy store. User asks a progress question; backend obtains appropriately audience-bound delegated access; training API validates token and fresh permissions; reads only permitted approved facts; returns provenance/freshness; primary backend answers. Include separate denied/stale-permission branch with no data disclosure. Do not use a broad machine directory token or arbitrary user ID header. Keep the frontier model outside direct credential/database access; it receives only permitted tool result data.

## D07 Member synchronisation and approved facts — dataflow

Sources: primary directory and admin-created local profiles. Transformations: signature/token validation, version/dedup checks, ownership rules, duplicate resolution, member mapping. Stores: member directory, sync ledger, outbox, training approvals. Consumers: local learning screens and primary platform approved facts. Branch local_only/synthetic exclusion at every outbound route. Promotion request -> primary acknowledgement -> verified mapping -> linked. Outbound approved events have an at-least-once route and reconciliation cursor; stale/out-of-order inputs rejected. Annotate data classes, trust crossings, ownership and deletion/suspension propagation.

## D08 Programme and enrolment lifecycle — lifecycle

Keep this focused on enrolment: onboarding -> ready -> active -> paused/resumed -> completed or cancelled. Readiness requires baseline approval. Completion requires sufficient approved evidence and human final approval. Programme content version states appear in cards or a subordinate clearly separated state model, never mistaken for enrolment states. Include time/calendar effect of pause, invalid activation prevention and no automatic completion from time spent.

## D09 Submission attempt lifecycle — lifecycle

Draft -> submitted -> queued -> grading -> review_pending -> approved or changes_requested. Technical failure permits bounded retry; resubmission creates a NEW attempt. Regrading creates a new grading attempt on the existing submission; an approved decision is immutable and can be superseded by a recorded review. Include permitted withdrawal before approval and appeal route. Explicit terminal states; keep history and identities clear.

## D10 Weekly adaptation and freeze cutoff — workflow

Approved Week N evidence -> select N+2 -> eligibility/freeze/activity check -> AI proposal within budget -> schema/calendar/prerequisite validation -> coordinator review -> recheck inputs/target/version -> publish and notify. If missed cutoff, stale evidence or target started, keep approved baseline and select later eligible target. If none remains, final support/follow-on. Include leave/calendar rebase. Worked card: W1 result reviewed W2; W3 revised by Thursday 17:00 before Monday start. Show current+next readiness separately from freeze; do not use current_week+2 to accidentally force W1 results into W4.

## D11 KPI lineage and improvement suggestions — dataflow

Source assessment and approved rubric observations -> normalisation/comparability/sample check -> versioned KPI calculation -> approved snapshot -> member dashboard, coordinator report and permitted primary API. Distinguish participation, competence and workplace outcome measures. Insufficient sample yields insufficient_evidence. AI suggestions draw from KPI gaps and remaining capacity; changing required work passes a coordinator approval gate. Targets/definitions are human-owned and effective-dated. Trace formula, baseline, target, rationale and evidence in cards.

## Cross-view consistency

Use consistent names, record ownership and arrow direction. Directory sync credentials and delegated user credentials are distinct. Future baseline content exists before AI adaptations. Current work and approved historical attempts are immutable. Native diagram validation checks rendering; the acceptance scenarios check semantic completeness. Both are required before describing the suite as complete.
