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
