# Shared contract register

Maintainer and technical self-reviewer: Bernie. UI consumer and UX/accessibility reviewer: Saturnino. Project owner accepts milestone outcomes. All entries below are **proposed v0**; they describe required semantics, not existing APIs, JSON schemas or frozen interfaces. M0/M1 must inspect code and publish concrete schemas/fixtures before binding consumers. External Cerebru contracts must come from actual documentation and sandbox evidence.

| ID | Boundary | Producer | Consumers | Required semantics | State |
| --- | --- | --- | --- | --- | --- |
| C01 | Actor and client authorisation | Bernie | Saturnino UI consumer / all modules | Verified actor, calling client where applicable, active training client/environment, scoped grants, effective team/member relationships, permission version/freshness; standard denial semantics. | proposed_v0 |
| C02 | Member and team selection | Bernie | Saturnino UI consumer / assessment import UI | Client-scoped stable IDs, source ownership, effective memberships, coordinator assignments, filtering before pagination/totals; no cross-client joins. | proposed_v0 |
| C03 | Assessment assignment and approved result | Bernie | Saturnino UI consumer / M4 | Definition/rubric/attempt versions, actor ownership, evidence references, provisional analysis versus human decision, approved member-visible summary. | proposed_v0 |
| C04 | Programme and capacity calendar | Bernie | Saturnino UI consumer / M5 / M6 | Duration mode, explicit dates/timezone/working calendar, approved source assessment, plan/version status, activities/time, approval/freeze and shared capacity. | proposed_v0 |
| C05 | Weekly progress and private notes | Bernie | Saturnino UI consumer / M5 / M6 | Versioned period/plan, self-report versus approved evidence, submission/receipt, notes sender/assigned recipient and reassignment; no automatic chat ingestion of private notes. | proposed_v0 |
| C06 | Approved chat context and answer | Bernie | Saturnino UI consumer / M6 | Scoped actor/conversation, approved permitted context/source versions, bounded request/answer, citations, permission recheck, usage and safe errors; no arbitrary tools. | proposed_v0 |
| C07 | Reporting projection and export | Bernie | Saturnino UI consumer / M5 | Authorised rows before aggregate, versioned definitions/filters, compatible scales, missing-data handling, export requester/client/scope versions, expiry and current access at download. | proposed_v0 |

## Acceptance record per contract

Record schema/fixture file paths, version, producer commit, Bernie's technical self-review, Saturnino's applicable UI-consumer findings, project-owner acceptance date where required, change log, compatibility, error/idempotency/version rules and checks. Leave unknowns explicitly unknown. Consumer silence is not acceptance. Do not manufacture another person's decision.

Use fixtures for allowed and denied client/team/member cases, missing/stale data, optimistic conflicts and dependency failure. APIs return scoped data, not broad records filtered by the browser/model. No concrete Cerebru endpoint, token claim or source-ID field is declared implemented by this register.

For changes: propose → review → accept version → implement producer/consumer → integrate/test. Any safety-impacting change reruns affected foundation denial cases. Shared-file edits follow COORDINATION.md.
