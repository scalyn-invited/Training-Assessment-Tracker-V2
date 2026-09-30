# Proposed entities and interfaces

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


## Contract inventory

  /api/v1/me/training:
      operationId: getOwnTraining
  /api/v1/members/{memberId}/summary:
      operationId: getMemberSummary
  /api/v1/groups/{groupId}/progress:
      operationId: getGroupProgress
  /api/v1/changes:
      operationId: getApprovedChanges
  /api/v1/integrations/directory/events:
      operationId: receiveDirectoryEvent
  /api/v1/jobs/{jobId}:
      operationId: getOwnJob
  /app-api/v1/programme-versions/{versionId}/review:
      operationId: reviewProgramme
  /app-api/v1/submissions:
      operationId: submitAttempt
  /app-api/v1/grading-attempts/{attemptId}/review:
      operationId: reviewGrade
  /app-api/v1/adaptations/{proposalId}/review:
      operationId: reviewAdaptation
  /app-api/v1/members/{memberId}/promotion:
      operationId: requestPromotion

Application schema definitions: ResourceRef, RubricCriterion, Task, Day, Week, ProgrammeDraft, CriterionScore, ProvisionalGrade, AdaptationProposal, DirectoryEvent, ApprovedTrainingEvent.

The OpenAPI contract is proposed; primary platform endpoints remain unknown. Stale permission/dependency uses 503 in the proposed contract, invalid authentication 401, absent operation scope 403, inaccessible object 404, concurrency/freeze/idempotency conflict 409. The D06 denied branch lists common denials; stale 503 is clarified here.
