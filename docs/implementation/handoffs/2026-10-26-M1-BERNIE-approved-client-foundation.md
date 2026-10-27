# M1 Bernie approved client foundation handoff

Date: 2026-10-26, Asia/Manila. Contributor: Bernie, Developer A.
Branch: `codex/m1-approved-client-foundation`; base commit `418dd7f5c2237d1c11d73bced4e6b11a26a4eca7`; delivery is uncommitted working-tree changes.
Status: local foundation component ready for review; full M1 partial. Product architecture approval does not constitute acceptance of this implementation.

## Authority and scope

See [approved decisions](2026-10-26-approved-architecture-decisions.md), sourced from the user's architecture approval and subsequent identification as Bernie in this conversation. D01/D02 business direction is no longer blocked on the old one-client assumption. D03 Cerebru source contracts remain deferred. No paid/live operation approval is inferred.

This is a bounded additive foundation component, not the full application cutover. It supplies executable client directory mutations and access predicates for new consumers, with relational integrity and tests. Existing learning routes, jobs, file downloads, reports and AI still use the original Access/Administration services and user fields. The new policy must NOT be advertised as protecting those existing paths until integration is implemented and tested. No actual application database was migrated or backfilled.

## Files and compatibility

- `platform/database/migrations/2026_10_26_120000_create_client_foundation.php`: adds client memberships, three-role catalogue, scoped grants, coordinator allocations and new dated team membership records. Adds nullable explicit group kind and tenant/actor composite integrity. Existing group types, roles and memberships are not guessed or backfilled.
- `platform/app/Services/Tenancy/ClientAccess.php`: fresh identity/account/client checks, scoped Admin authority, team Coordinator authority, limited training-summary view/manage predicates and client-Admin directory query.
- `platform/app/Services/Tenancy/ClientAdministration.php`: authorised, transactional membership join/leave; explicit team/cohort creation; dated team join/leave; role grant/revocation; primary/backup allocation and explicit allocation end. Mutations audit client context and invalidate sessions/permission versions.
- `platform/tests/Feature/ClientFoundationTest.php`: executable positive/negative fixtures.
- `platform/tests/Feature/ClientFoundationMigrationTest.php`: isolated SQLite upgrade/empty rollback/re-upgrade rehearsal and exact legacy-row comparisons.
- This handoff, approved decision record and additive Bernie claim/delivery entries.

Transitional design: `client_team_memberships` lives beside legacy `memberships` so old writers are not silently changed. New consumers use only the new periods; old consumers use only the old table. This is not dual-authority unioning. A future reviewed backfill/cutover must retire the old writer before removing legacy state. The new role catalogue contains exactly admin/coordinator/member. A client-scoped coordinator grant permits explicit teamless allocation only; it is not client-wide training visibility.

## Internal C01/C02 service contract

- Every operation takes an explicit client or persisted membership identifier. Authority is never derived from legacy `users.role`, `users.organisation_id` or `users.sensitive_access`.
- `ClientAccess::administer(actor, client)` covers directory management only. Global Admin does not automatically satisfy training-summary predicates.
- `ClientAccess::viewTraining(actor, memberMembershipId, persistedRecordTeamIdOrNull, persistedRecordedAt)` covers NON-SENSITIVE summaries only. It does not authorise private notes, raw assessments, file downloads, answer keys or exports. Consumers must load persisted ownership within their scope; request-supplied owner/team/time values are not authority.
- Team records require current member/team membership and coordinator/team authority dating back to the record. Later transfers do not automatically open old records. Teamless records require explicit allocation (or the member's own Member grant).
- `manageTraining` additionally requires the primary allocation and prohibits self-approval. Backup-only allocations can view allocated teamless summaries but cannot approve. Backup activation remains undefined and is deliberately not automated.
- Departure ends local grants, team periods and allocations without deleting history or disabling a shared global user. Re-entry preserves the client relationship ID but does not revive ended roles/allocations. Team periods append after re-entry instead of rewriting the first period. Revoking a coordinator role conservatively ends that coordinator's existing member allocations; any reinstatement is explicit.
- Client Admin can allocate teamless members. Creating/revoking a client Admin role requires Global Admin. There is no unauthenticated Global Admin bootstrap or public grant endpoint; platform bootstrap and reauthentication flows remain future integration work.
- Errors: 403 insufficient operation authority; 404 foreign/inaccessible client member/team/allocation; 409 duplicate active relationship, stale membership version or occupied primary slot; 422 invalid role/type/self-allocation. Validation raises Laravel validation exceptions.
- Service transactions serialize mutations on the organisation row. Composite FKs reject cross-client teams and membership-owner mismatches. MariaDB concurrency behavior remains unverified.
- No HTTP/UI contract has been published or frozen. These are tested internal producer contracts, not permission to bind UI against invented routes.

## Verification evidence

- Initial restricted PHP run could not read vendor/autoload.php. Retried with the approved execution permission; database configuration forces in-memory SQLite, mail array sink and synthetic test environment.
- Initial targeted run: 13 tests, one test-fixture failure (422 before expected 409 because the proposed replacement lacked a coordinator role). Corrected the redundant fixture; the dedicated primary-replacement test covers the 409 path with authorised coordinators.
- Regression run: `php vendor/bin/phpunit --display-skipped` reported 127 tests, 670 assertions, four utility skips; no failures. Skips: two ZipArchive cases, real Poppler/Tesseract integration and real ClamAV integration. These are incomplete utility checks, not clean results.
- `php vendor/bin/pint --test app/Services/Tenancy tests/Feature/ClientFoundationTest.php tests/Feature/ClientFoundationMigrationTest.php database/migrations/2026_10_26_120000_create_client_foundation.php`: passed before the final rollback-classification guard; final verification is recorded below.
- Migration rehearsal uses a separate disposable `:memory:` SQLite connection. It compares users, organisations, groups, legacy memberships, coordinator assignments, enrolments and evidence metadata before/after upgrade and empty rollback, then re-upgrades. No claims about populated production backfill, approved-result migration, interrupted MariaDB DDL recovery or production restore.
- MariaDB test connection at 127.0.0.1:3308 unavailable. No MariaDB database was created, migrated or reset. MariaDB migration/concurrency qualification remains blocked on an available isolated test instance.
- No browser checks: no UI or HTTP integration changed. No authentik instance, live identity, Cerebru, SMTP or AI calls were made.

## Technical self-review

Implementation self-review performed in Bernie's session, not independent review. Checked same-client/actor FKs, service authorization before mutation, explicit client audit ownership, revocation with stale actor objects, no Admin-sensitive bypass, self-approval prevention, one primary coordinator, backup fail-closed behavior, explicit history preservation and no legacy policy union. Fixed missing membership-owner/environment integrity and protected rollback from discarding typed-group decisions. No user-only identity can gain client authority from legacy role fields.

The populated down migration intentionally refuses deletion of memberships/grants/allocations or typed-group classifications. Roll back unused empty additions only; otherwise restore/reconcile using the future qualified rollout procedure. This is a safety boundary, not a zero-downtime rollback claim.

## Remaining delivery gates

Final verification after the rollback-classification guard: full suite 127 tests (123 passed, four skipped), 670 assertions, no failures, 14.645 seconds; targeted Pint passed; `git diff --check` passed. No further PHP changes followed these checks.

Reviewed source SHA-256 anchors:

| File | SHA-256 |
| --- | --- |
| ClientAccess.php | 7A2993C00FB4BB24AD24AA9E77DE6C674396D023146FD776DC3D6BB3F4829DB1 |
| ClientAdministration.php | 48AA8FB0DCD3366D85DA70E6529AA7071B2F0F47D3864BAA46C5DDD074D5DF87 |
| 2026_10_26_120000_create_client_foundation.php | C114BDE58E45154CC8BE3F3F016B6FF87CFADDD374E5964E720F0EE365B0E683 |
| ClientFoundationMigrationTest.php | FF32C3092158C5A8A8016DEA79008B3B078C8D3BEEEABBD31B8B4D7B151A21AC |
| ClientFoundationTest.php | E79D39CD4715833E0DEF3748DB60ED11F4C020C14C4FCDD44F3DF03B445E21BB |

1. Qualify MariaDB integrity and concurrent primary/grant updates on an isolated instance.
2. Inventory and explicitly map existing users/groups/grants; preserve immutable results and evidence in a real backfill rehearsal.
3. Cut over HTTP, Livewire, worker, file, report, approval and AI consumers together, including sensitive capabilities and limited departing-member history grants. Do not deploy the new model as a completed security cutover before this.
4. Implement client management UI and persisted branding; obtain Saturnino UX/accessibility review after stable consumer contracts exist.
5. Implement authentik OIDC using actual instance/configuration and approved MFA policy; no guessed issuer/client/claims.
6. Implement standalone/teamless assessments, programme distribution, shared cross-client capacity, retention configuration and remaining training features in their bounded milestones. Notice/access durations and backup activation remain unspecified.
7. Ticket-linked PR/hosted CI and subsequent acceptance remain pending. No remote push, PR, merge or release performed; repository instructions require specific push authorisation.

Preserved: pre-existing Bernie changes, earlier C01/C02 proposal artifacts, public images and Word lock file. Saturnino's files, shared layout and legacy Access/Administration code unchanged. No cross-machine acknowledgement or external message is claimed. Actual hours unknown.
