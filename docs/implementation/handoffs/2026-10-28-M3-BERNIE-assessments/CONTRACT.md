# C03 v1 — Initial assessment assignment and approved result

Producer: Bernie M3 implementation. Consumers: bounded assessment screens and future M4. This is a locally implemented contract, not project-owner acceptance or live Cerebru proof.

## Identity and ownership

All routes use TrainingSession, the selected client, CSRF protection, active identity and current permission version. No bearer-token/public integration API is added. Definitions belong to a client and environment; assignment permissions reuse the existing enrolment, primary allocation and explicit sensitive-access grant. A Global Admin title alone never permits assessment content. Queries filter before pagination. Members can access their own eligible records only.

An assignment requires an existing enrolment but requires no programme, learning block or lesson. Client administrators retain the existing enrolment/coordinator provisioning workflow. Unavailable eligible members are explained in the assignment UI.

## Lifecycle and versions

- Definitions contain a title, stable question IDs/prompts, category/criterion IDs, scoring guidance, positive weights totaling one, and an optional 0–100 threshold. No default pass threshold exists. Draft saves use `expected_version`. Publishing freezes the version; editing it creates the next family revision.
- An assignment pins the published definition. Assignment is idempotent for definition/enrolment. States are assigned, draft, submitted, queued, review_pending, technical_failure, approved and rejected. Its version is the optimistic concurrency token.
- Draft saves accept `responses` keyed by question ID, optional context, and up to five scanned accessible file IDs. Submission uses the saved assignment version plus a UUID idempotency key. Same key/version/assignment returns the original receipt; mismatched reuse conflicts. Attempts preserve encrypted snapshots, hashes and monotonically increasing attempt numbers.
- Rejection preserves the decision and attempt, and permits a new saved response/attempt. Open appeals block resubmission. Approved results remain visible while an appeal or re-analysis is pending.
- Review rows preserve ordered analysis/decision history. Approvals can cite a provisional source review; edits do not replace the original model result. Decisions require exact assignment version, the latest attempt, current reviewer authority and a reason. Self-approval is denied. Pending analysis must finish before a decision.

## Evidence and scoring

Written-response evidence IDs are `attempt-id:question-id`. Uploaded file IDs alone are never scoring evidence. Clean file content must be explicitly confirmed by an authorised reviewer; confirmation records preserve the source hash, actor, reason and encrypted text. The confirmation ID is the scoring evidence reference. Scoring rechecks clean status, source bytes/hash and current confirmation.

Extraction reuses the bounded document runner and production sandbox requirement. Extracted text is a private draft, not confirmed evidence. Manual verified transcription is available when extraction is unavailable. Quarantined, rejected, changed or inaccessible files remain unusable. No scanner or extraction restriction is bypassed in localhost production.

Analysis/decision input has `rubric_id`, `rubric_version`, and one row per criterion: `id`, nullable `score`, `evidence_ids`, `feedback`, `insufficient_evidence`. Scores are 0–100. Missing evidence produces null scores and null affected category/overall totals. Category scores normalize the criterion weights within that category. The overall total sums all weighted criteria. Application code computes totals; supplied totals are not accepted. Threshold success is null when missing evidence or no approved threshold exists.

M3 AI uses the existing durable gateway, budget reservations, provider policies, bounded recovery and execution/publication permission checks. `assess_initial` has no programme ID. It is restricted to synthetic local/testing data for this delivery. Real/production operation uses manual review; live AI remains a separate approval and qualification gate.

## Review, appeals and member projection

Members receive pending/failure status and approved criterion explanations, category totals, overall total and optional threshold outcome. Private decision reasons, raw AI output and reviewer history are excluded. There are no initial-assessment answer-key fields in this version.

Members may appeal a decision. A Client Admin explicitly assigns a different active coordinator for the same team, excluding the member, original reviewer and assigning admin. The pinned role grant is checked on each access; revocation closes access. Case authority permits only that assessment and its submitted files. Uphold or a replacement decision resolves the appeal and ends case access. It does not grant ongoing sensitive access to the member's other records.

The M4 handoff is `C03-v1`, assignment/attempt/decision IDs, definition/revision, approval timestamp and the approved result. `plan_creation_available` is false until M4. It contains no raw submission, private reason or automatic plan-generation command.

## HTTP contract

| Route | Behavior |
| --- | --- |
| GET `/assessments` | Scoped library, assignment list and admin-only minimal appeal routing queue |
| GET `/assessments/definitions/new` or `/assessments/definitions/{id}` | Coordinator authoring |
| POST `/assessments/definitions` | `save`, `publish`, `assign`; version checked on edits/publication |
| GET `/assessments/{id}` | Scoped page; JSON returns id, status, version and approved projection only |
| POST `/assessments/{id}` | `save`, `submit`, `analysis`, `confirm`, `extract`, `approve`, `reject`, `appeal`, `uphold`; exact versions on state transitions |
| POST `/assessments/appeals/assign` | Client-admin alternate-reviewer assignment with reason |

Existing private upload/download routes are reused. Authentication failures retain the existing browser login behavior; missing operation authority is 403, inaccessible objects 404, stale versions/idempotency conflicts 409, invalid content 422 and stale imported permissions 503. HTML form operations redirect on success; JSON assignment mutations return id/status/version. No integration-specific authentication semantics are implied.

## Qualification boundaries

Local PHP, MariaDB/concurrency, browser and migration evidence are listed in the companion handoff. Postman examples use the repository's existing v2.1 local artifact convention and empty environment values. No Postman workspace publication or external collection execution is claimed. Human UX/accessibility review, actual business rubrics, calibrated live scoring and milestone acceptance remain pending.
