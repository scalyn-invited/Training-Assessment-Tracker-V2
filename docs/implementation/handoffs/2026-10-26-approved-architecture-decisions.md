# Approved architecture decisions

Approved by Bernie in the current conversation on 26 October 2026 (Asia/Manila): architecture and defaults approved with “All approved”; contributor identity subsequently confirmed. This records product authority, not implementation acceptance, technical review, release or cost approval.

## Decisions superseding earlier proposals

- D01: organisations are clients; groups have distinct internal-team and training-cohort records. Members can belong to multiple clients and multiple teams per client, or remain teamless. Never infer the type of an existing group or backfill by its label.
- D02: exactly Admin, Training Coordinator and Member; multiple roles per client allowed. Admin has global or client scope. Coordinators can view their team's training records but manage/approve only allocated members. One primary accountable coordinator, explicitly assigned backups. Client Admin explicitly allocates a coordinator to a teamless member. No automatic team visibility for teamless assessments. Global Admin manages the system, not unrestricted sensitive information. No self-approval.
- History stays at its client; removal updates access. Optional departing-member history access is a separate limited read-only grant, not membership. No automatic history transfer or private reviewer-note disclosure.
- D03 direction: authentik now, standards-based authentication; Cerebru integration deferred and existing integration preserved. No instance URL, client registration, credentials, MFA claims or live contract supplied/approved.
- Programmes can target clients, teams or individuals. Later joiners automatically enrol in ongoing mandatory programmes; fixed/completed programmes require explicit enrolment. Capacity conflicts must remain visible.
- Overall workload capacity is shared across clients; allocations/reporting remain client-scoped without disclosure of other clients' details.
- D04: 30 calendar days. D05: retain N+2 and existing freeze/approval safeguards. Do not rewrite legacy programme durations.
- Reject means correction/resubmission; preserve prior attempts and decisions.
- Configurable client retention: completed assessments five years after completion; rejected assessments five years after final decision; training completion records five years after completion; certificates five years after expiry where applicable; private notes two years after last relevant activity; security/access logs twelve months; inactive drafts 180 days with advance notice; inactive accounts disabled after twelve months then reviewed for deletion. System-wide security/audit minimum and legal holds override client shortening. Active correction cycles are not deletion candidates.

## Still unspecified

Advance-notice duration, departing-member access duration, exact system minimum enforcement details and backup approval activation procedure are not inferred from approval. Keep dependent automation disabled/unset until specified. A backup assignment alone must not silently transfer primary approval authority.

## Foundation delivery boundary

First implementation slice is additive. New memberships and grants are explicit; existing users/roles/groups/enrolments are not silently backfilled. Existing learning routes remain on their original access model until all request, worker, file, report and approval consumers can be cut over together. Passing foundation tests is not completion of M1 or deployment permission. No paid services, real notifications or production data are authorised by this architecture approval.
