# Proposed architecture decisions

This is a local design, not an implemented or deployed platform. The supplied v2 specification and policy rules are authoritative.

# Decisions and defaults

Proceed with these defaults during local design; do not pause for answers. They are proposed decisions. Unconfirmed integration facts remain labelled and must be resolved before live use.

| ID | Decision | Default and rationale | Confirmation boundary |
|---|---|---|---|
| ADR01 | Runtime | Laravel modular monolith, Blade/Livewire, MariaDB, DB queue; portable and few services | Pin supported versions during build |
| ADR02 | Production host | Dedicated Proxmox Linux VM; 2 vCPU/4 GB/40–60 GB training estimate | Benchmark actual workload |
| ADR03 | cPanel | Conditional alternate with external identity and qualifying CLI/cron limits | Do not claim qualified without host evidence |
| ADR04 | Identity | Assume authentik at goauthentik.io, OIDC + MFA, separate service | User's original domain/name remains unconfirmed |
| ADR05 | SMTP | SMTP2GO via configurable SMTP/TLS; test sink for development | Verify sender/domain before live email |
| ADR06 | Data ownership | Primary directory owns imported people/groups; app owns training approvals; primary consolidates approved facts | Actual primary API and ID mapping unknown |
| ADR07 | Local/test separation | Real local-only members have local reports; synthetic test records stay out of production | No automatic promotion or history export |
| ADR08 | Model selection | Per-task model registry, two adapters + mock, one calibrated live provider initially | Exact models/pricing/privacy settings selected at implementation |
| ADR09 | Calendar | Asia/Manila, Mon–Fri, coordinator-selected 15–120 minutes; 4/6/8/10/12 weeks | Visible editable onboarding defaults |
| ADR10 | Adaptation | N+2 target; target cutoff 17:00 on second business day before start; baseline fallback | Calendar/holiday aware and configurable |
| ADR11 | Human review | Plans, grades and adaptations require coordinator approval; local file generation does not | Preserve these gates in every diagram |
| ADR12 | Scale | 100 members / 20 concurrent as pilot sizing assumptions | Actual headcount unknown |
| ADR13 | Recovery | RPO 24h / RTO 4h proposal inherited from v1 | Business acceptance or stronger schedule before launch |
| ADR14 | Retention | Evidence/audit 24 months, sensitive AI traces 30 days proposed | Review actual obligations and provider controls |
| ADR15 | Performance | p95 ordinary reads <500ms server; initial usable dashboard <2s on agreed network | Targets only; run load tests |
| ADR16 | API exposure | Private route preferred; HTTPS audience/scopes + record policy regardless | Primary platform location/network unknown |
| ADR17 | Archify run | Local diagrams only; all routine writes/validation authorised; preserve unrelated files | Host system/tool permissions still apply |

## Unknowns that must not block diagram generation

Real domain names, private IPs, actual user count, source-platform API specification, named incident owners, sender domain, real credentials, exact AI models, data region and logo. Use role labels and example.invalid URLs. Show integration contracts as proposed, not observed. Record a concrete deployment checklist for these unknowns.

## Resolving document conflicts

The user requirements and v2 rules take precedence over v1 wording. In particular: (1) real local-only is not the same as synthetic; (2) two weeks ready is not two weeks permanently frozen; (3) model switching must be implemented through provider adapters, not just a dropdown; (4) Archify validation does not certify the application.


## Tradeoffs and operational consequences

A modular monolith keeps policy checks, approvals, durable jobs and the transactional outbox in one codebase and database transaction. Blade/Livewire keeps the browser thin. MariaDB queue/locks avoid an initial Redis dependency; queue and database contention must be measured in the pilot. Separate extraction, generation, grading, integration and notification queues prevent long AI work from starving submissions.

Proxmox gives control of PHP CLI, extraction utilities, process supervision, private storage and backup orchestration. Its cost is ownership of OS patching, restore testing and monitoring. One training VM is not high availability. The 2 vCPU / 4 GB / 40–60 GB allocation is only a starting estimate. Identity has separate capacity and lifecycle.

cPanel reduces infrastructure work but remains NOT QUALIFIED. Prove PHP/CLI equivalence, extensions, transactions, per-minute cron, overlap locks, private paths, outbound TLS, quotas and worker runtime. If a whole 120-second job cannot complete safely or extraction utilities are missing, prefer moving the entire application to Proxmox. Do not introduce an unapproved remote-worker hybrid.

Whole-programme baseline approval costs coordinator time up front but ensures continuity during late reviews or provider outages. Current and next week stay ready; readiness does not permanently freeze future content. N+2 is calculated from evidence week N. Target freeze is 17:00 on the second business day before start or immediately on activity.

Primary directory ownership and training approval ownership are deliberately distinct. Promotion is explicit and idempotent. Real local-only people are valid local learners/report subjects; synthetic records never enter production. Delegated questions require server-side current record permissions; machine directory credentials cannot replace user delegation.

AI is an untrusted proposal mechanism. Two adapters plus a mock expose per-task capabilities, privacy policy, pricing and budgets. Human reviewers own targets, grading approval and required work. Store exact versions and rationale; provider errors do not become zero scores.

## Implementation confirmations

Confirm authentik product/instance, source API and delegated token exchange, person-ID mapping, network route/domains, SMTP sender, exact models and data region, backup obligations, retention approval, owners, actual headcount, holiday calendar and host capability. None prevents authoring the local design. No credentials or staff data were used.
