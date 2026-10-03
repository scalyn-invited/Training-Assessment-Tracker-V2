# Representative pilot and launch decision

The automated pilot is synthetic: `PilotWorkflowTest` completes onboarding, baseline approval, 12 lessons, provisional mock grades, explicit human-review fixtures, sufficient KPI samples and completion. It proves application transitions, not educational effectiveness or AI accuracy.

Before a real pilot, record named owners and dates for each gate:

| Gate | Required evidence | Current state |
| --- | --- | --- |
| Identity | Actual OIDC issuer/client, PKCE/state/nonce/signature/audience/expiry tests, MFA policy, immutable person mapping, deactivation and delegated-token denial cases | No service/contract supplied; live adapter unavailable |
| Primary integration | Actual create/reconcile/directory/feed contracts, verified mappings, duplicate/timeout tests, scoped retrieval, tombstone receipt and consumer cache purge | Local contract harness only |
| AI | Exact models/regions/data classes/pricing, per-task capabilities, prompt/schema versions, bounded failure proof, consent/data processing approval | Mock plus HTTP-faked provider adapters; live disabled |
| Assessment calibration | Representative practical work and missing/adversarial evidence; two independent human reviewers; criterion-level agreement and supported override reasons; no unexplained access flaws | Not run with real staff |
| Workload | Measured learner minutes including revision; coordinator queue/review time; escalation threshold and backup reviewer | Synthetic workflow only |
| KPI validity | Named owners, comparable units/evidence, effective targets, sufficient samples, demonstrated competence distinguished from workplace outcomes | Mechanics tested; business definitions unapproved |
| Communication | SMTP sender/domain, minimal link content, opt-out, transient/permanent/ambiguous failures, authenticated delivery/bounce evidence | Local sink and isolated failure tests |
| Host/security | Supported patched runtime, TLS, private storage, ClamAV/ZipArchive, CLI limits, permissions, retention/hold/erasure procedure, incident owner | Templates and local tests only |
| Recovery/performance | Encrypted offsite MariaDB/files/keys/IdP restore; latest deletion ledger; controlled outbox replay; agreed RPO/RTO; representative host/device load | Synthetic SQLite drill and development-server measurement |
| Release decision | Pilot findings, unresolved issue log, rollback owner, go/no-go approver and support contact | Unassigned |

Use a cohort covering relevant roles, experience, accessibility needs and programme types. Record consent/authorised purpose before using real assessments. Run shadow/provisional grading first; only a coordinator decision changes official results. Compare AI and human criterion scores, evidence citations, missing-evidence handling, overconfidence and subgroup error patterns. Set acceptance thresholds before reviewing outcomes; fixed mock scores cannot contribute to the calibration dataset.

Record each case with a pseudonymous case ID, rubric/prompt/model versions, expected evidence, independent reviewer decisions, model provisional result, override rationale, observed minutes, cost and final adjudication. Keep the dataset private under the approved retention policy. Summarise disagreements and remediate before expanding the cohort.

Release only after the owner signs each applicable gate. A reduced cohort or one live provider may reduce operational scope; it does not prove untested model switching, promotion, recovery or identity contracts. The four blocked architecture layouts and their separate T26 visual checks are design-package work, not completed by these application tests.
