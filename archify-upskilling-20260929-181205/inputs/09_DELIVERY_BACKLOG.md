# Delivery backlog and completion gates

No implementation dates or costs are promised. Estimate after the integration proof and team review. Every increment retains the permission and versioning model; do not bolt it on after AI features.

| Increment | Deliverable | Dependencies | Completion gate |
|---|---|---|---|
| B01 Identity and primary API proof | OIDC/MFA, directory sample adapter, delegated query, account/group deactivation | Confirm product, identity instance and primary API sandbox | T01–T03, T13; documented trust contract |
| B02 Foundation | Laravel modular monolith, migrations, group policies, local/manual identities, private files, audit, queue/outbox | B01 contract; synthetic adapter can unblock local work | T04–T05, T18, T27, T30 |
| B03 Onboarding and learning content | Resumable wizard, assessment confirmation, competencies, calendars, versioned curriculum editor | B02 | T06, T10–T12, T24 |
| B04 AI gateway | Two provider adapters plus mock, registry, per-task routing, budgets, schemas, resource grounding | B03; no live provider needed for contract tests | T15–T16 and schema validation; later one calibrated live provider |
| B05 Learner delivery and review | Actual lessons, submissions, grade attempts, coordinator review, resubmit/appeal, SMTP queue | B03–B04 | T17–T19, T21, T24 |
| B06 KPIs and adaptation | Versioned measures, lineage, capacity-aware suggestions, Week N+2 proposal and cutoff | B05 | T07–T12, T20, T25 |
| B07 Source platform integration | Promotion, approved change feed, query permissions, cache invalidation, tombstones | B01/B02/B06 | T01–T05, T13–T14, T19 |
| B08 Deployment and operations | Proxmox deployment, cPanel qualification notes, backup/restore, monitoring, retention | All core increments | T22–T23, T28–T30 |
| B09 Pilot and release | Representative cohort, documented calibration, review workload, spend and support ownership | B08 | Agreed effectiveness and operational gates; no unresolved access flaws |

## Launch scope

Include all original requested capabilities: roles/groups; imported and manual members; selective sync; onboarding plus assessment upload; duration/time choices; AI provider/model switching; reviewed generation; learner submissions; AI grading with human review; SMTP notifications; weekly adaptation; KPI updates/suggestions; secure primary AI API. A reduced pilot may activate fewer members or one live provider, but must not claim model switching or selective sync works without verification.

## Explicitly deferred

Commercial multi-tenant subscriptions, SCORM/xAPI packages, native mobile apps, full video hosting, production code execution sandboxes, plagiarism/AI authorship scoring, automated employment decisions and fine-tuning. Architecture uses extension points for these rather than adding them to the lightweight release.

## Deployment handoff contents

Build/release instructions; .env.example with placeholders only; migration/rollback guide; process supervision or cPanel cron procedure; OIDC client setup; SMTP sender setup; primary sync runbook; provider model policies; alert ownership; restore drill; retention policy; coordinator and member quick guides. The implementation agent must supply executable artifacts and tests for its actual codebase, not present this design pack as runnable software.
