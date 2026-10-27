# Existing-data mapping dry run

Date: 2026-10-26. M1-BERNIE. This is an inventory and proposed mapping, not an executable backfill or approval to migrate.

## Source and preservation

Inspected `platform/database/database.sqlite` directly with SQLite URI `mode=ro`, `PRAGMA query_only=ON` and a read transaction. No Laravel bootstrap or migration ran against this file. All five users were explicitly synthetic and in the test environment. The tool refuses non-synthetic/non-test users.

Source SHA-256 before and after inspection, and rechecked after qualification:
`DE0CA5B1523B8DE5AD1DB7765533B3BB87090E163F4EA6FD08C69285B9CA7334`.

[Machine-readable evidence](mapping-dry-run.json) contains all table counts and sorted row hashes, schema hash, candidate identifiers and findings. It omits names, email addresses, identity subjects, credentials, assessment bodies and storage keys. Group names below were separately inspected for the user's classification question. No mapping has been applied.

## Proposed disposition

| Existing structure | Observed | Proposed handling before any future backfill |
| --- | ---: | --- |
| Organisations | 1 | Preserve organisation ID and historical ownership. |
| Users | 5 | Five candidate client memberships; preserve global identities. Review dates/status before creating anything. |
| Legacy roles | 1 admin, 2 coordinators, 2 members | Review explicit client/team grants. Never promote legacy admin to Global Admin automatically. Sensitive access requires separate capability review. |
| Groups | 2 | Explicitly classify each as team or cohort; do not infer from its name or origin. |
| Memberships | 2 | Retain legacy rows; only approved internal-team relationships may become dated team memberships. Cohort enrolment is separate. |
| Coordinator assignments | 2 | Candidate team authority only after group and role review; cohort membership does not confer team visibility. |
| Enrolments | 2 | Retain historical ownership and IDs; two candidate primary allocations require explicit current-authority/effective-date review. |
| Programme approvals / submissions / grade decisions | 1 / 1 / 0 | Preserve immutable history. The separate MariaDB rehearsal creates an approved grade decision to cover the case absent here. |
| Evidence / audit records | 3 / 140 | Preserve metadata, ownership and audit history; content/storage validation is outside this mapping report. |

## Group decisions still pending

| Group | Existing ID | Classification |
| --- | --- | --- |
| Operations | `01a0f221-a080-71df-bc53-c9d227539700` | Unresolved |
| Customer Support | `01a0f221-a086-73e4-b24b-c86dbd01f4b4` | Unresolved |

The user initially selected “one of each” but subsequently requested an explanation of team versus cohort, without assigning either type to a named group. No concrete mapping is approved yet. An internal team is an ongoing work unit; a cohort is assembled for training and can span teams. If both names describe departments, both can remain teams and separate cohorts can be created later. This explanation is not a decision.

## Findings and limits

The automated inspection produced two structural findings, both `group_classification_required`. It detected no missing-client user mapping, orphan/cross-client membership or enrolment, duplicate legacy relationship, invalid interval, mismatched coordinator-assignment role, self-review enrolment or competing primary candidate among the inspected relationships. This is not a complete business-history or whole-database integrity certification.

Legacy unique group/user records may have been reactivated in place. Do not invent lost membership periods from current timestamps. Enrolment coordinator IDs are historical candidates, not proof of current approval authority. The candidate list does not infer external identities, backup coordinator activation, sensitive privileges or departing-member access durations.

## Next mapping gate

1. Confirm the two group types and approve grant/effective-date and primary-allocation mappings.
2. Produce a reviewed mapping manifest separating internal-team membership from cohort enrolment, with conflicts explicitly rejected.
3. Rehearse that manifest on a separate synthetic copy, proving idempotency, preservation, access changes and recovery. The current MariaDB rehearsal validates additive schema changes; it does not execute this existing-data backfill.
4. Integrate all application consumers and establish the actual rollout/restore procedure before requesting any application database migration.

## Reproduction

From `platform/`, using Python 3.11 or newer:

```text
python -B scripts/client-mapping-dry-run.py --database database/database.sqlite --output <new-report-path.json>
python -B tests/Support/test_client_mapping.py
```

Output files are created exclusively: existing reports and the source database cannot be overwritten by the CLI. Four mapping tests cover no inferred classification/mutation, invalid relationships, conflicting primary candidates/no global-admin promotion, and refusal of a non-synthetic source without mutation. These are local checks, not hosted CI evidence.
