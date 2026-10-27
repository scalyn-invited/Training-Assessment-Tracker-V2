# M1 additive client/grant migration design — proposal

Date: 2026-10-26 (Asia/Manila)
Status: design proposal only; D01, D02 and D03 remain open.
Source revision inspected: `418dd7f5c2237d1c11d73bced4e6b11a26a4eca7`.

## Current schema boundary

The inspected foundation migration creates `organisations`; adds a nullable `users.organisation_id`, one `role` string and one `sensitive_access` Boolean; and scopes `groups`, `memberships`, `coordinator_assignments`, `enrolments`, evidence, audit and outbox records by organisation/environment. Groups have no explicit imported-team versus training-cohort discriminator or verified external-group mapping. Membership and coordinator-assignment rows are separate, but each currently has a unique `(group_id, user_id)` constraint. Enrolments reference a member, group and coordinator by ordinary foreign keys. This existing structure does not define accepted multi-client grants or same-client composite integrity.

The inspected migrations also attach user/organisation/enrolment identities to onboarding versions, member calendars, programme versions and approvals, capacity allocations, learning blocks, work drafts, submission/grade attempts and decisions, appeals, learning notifications, KPI observations/snapshots and adaptation proposals, AI policies/runs/blocks, promotions, document extractions, legal holds and related records. These relationships make history preservation a cross-module requirement. Before implementation, inventory every affected foreign key and record count from the exact database migration state; do not treat this list as a complete data inventory. Current grants, source IDs, cross-client staff behavior, transfer/history semantics and imported team mappings are not established by the local schema.

## Required decision inputs before schema work

- **D01:** accepted client-to-organisation relationship, member/client cardinality and ownership, imported-team versus training-cohort meaning, and any transfer behavior.
- **D02:** accepted grant subject and capability model, cross-client staff rules, sensitive-data capabilities, revocation, and treatment of existing history. Admin/platform-operations titles must not silently imply client-sensitive access.
- **D03:** actual source-owned immutable identifiers and mapping evidence before any directory/source mapping is populated. Do not use email as an identity key.

Until those decisions are recorded, all names below are conceptual; no proposed table, column, role, cardinality or backfill mapping is frozen.

## Additive migration sequence after the gates

1. **Inventory and baseline.** Pin source revision and migration state; enumerate affected foreign keys, unique constraints, row counts, active and historical membership intervals, approved results, audit records, evidence hashes and queued/outbox references. Take a verified restorable backup of the isolated MariaDB rehearsal database; do not mutate a shared or production database.
2. **Add mapping/grant structures.** Add new client/source-mapping and explicit capability-grant structures only after D01/D02/D03 define their ownership and keys. Initially nullable or side-by-side relationships should leave existing reads and records intact. Record version/effective-state and revocation semantics from the accepted contract, not this proposal.
3. **Backfill confirmed mappings.** Backfill only records whose client, team/cohort type and source identity have an approved, unambiguous mapping. Produce a dry-run manifest with candidate counts, unmapped rows, duplicate keys, orphan references and cross-client relationships. Require explicit disposition for every ambiguous row; do not auto-transfer history or infer links from labels/email.
4. **Validate before enforcement.** Compare pre/post row counts and stable identifiers; verify no orphan, duplicate or cross-client references; confirm approved assessment/plan history, file hashes, audit provenance and queue references remain unchanged. Test grant revocation and version changes. Resolve any invalid row before tightening constraints.
5. **Enforce accepted constraints.** Add the approved foreign-key/uniqueness and same-client relationship constraints only after MariaDB compatibility, existing data, lock impact and rollback behavior are demonstrated in the isolated rehearsal. Do not assume ordinary single-column foreign keys enforce same-client membership.
6. **Rehearse forward and rollback.** Run upgrade, interrupted/partial-stage recovery, rollback and re-upgrade on a restorable synthetic MariaDB copy. An additive rollback may remove only unused new structures or relax newly added constraints after dependent code is absent. Never delete or rewrite approved history, evidence, audit records or pre-existing rows as rollback cleanup.

## Proposed integrity and preservation checks

- Client/environment ownership agrees across every member, team/cohort, coordinator, enrolment, evidence and dependent learning/approval record.
- Imported teams and local training cohorts remain distinct typed relationships; membership alone never creates a reviewer grant.
- Grant uniqueness/effectivity and revocation behavior follow the accepted D02 contract; concurrent grant updates cannot revive stale access.
- Source mappings use verified immutable IDs and preserve source ownership; duplicate or conflicting source keys fail closed for manual resolution.
- Existing primary keys, approved decisions/results, version history, audit events, evidence hashes, pending outbox/job references and row counts are preserved.
- Pre-migration restore remains usable; no destructive history transfer or silent member reassignment occurs.

## Not performed in this slice

No schema names or migration files are created, no database is migrated, no data is backfilled, and no MariaDB or concurrency test is run. Those operations require accepted D01/D02 (and D03 for source mappings), a stable reviewed contract, an isolated synthetic database, and a separate implementation claim. The listed checks are acceptance scenarios, not completed evidence.
