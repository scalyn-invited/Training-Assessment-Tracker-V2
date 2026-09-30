# Requirement reconciliation

This is a manual source/card review, not a security certification or application acceptance execution. All 40 supplied requirement IDs are retained in REQUIREMENTS_COVERAGE.csv.

| Invariant | Views | Expressed design | Remaining gap |
|---|---|---|---|
| Human authority | D01 D04 D05 D09 D10 D11 | Plans, grades and adaptations require exact-version coordinator approval; no model approval and no self-grading. | D04/D05/D09/D10 native visuals blocked; written nodes/cards retain policy. |
| Data ownership | D01 D06 D07 | No primary-to-database edge. Primary owns imported people/groups; training owns approvals/evidence. | Directory ACK and promotion exchange are collapsed into mapping node/card rather than a separate primary exchange view. |
| Local-only and test | D03 D04 D06 D07 D11 | Real local-only included locally, excluded externally. Synthetic production report/export/mail rejected. | Explicit exclusions are in gates/cards; no executed serializers exist. |
| Adaptation | D08 D10 | Approved whole baseline; evidence N+2; target Thursday 17:00 for Monday start; early activity locks; later target or follow-on fallback. | D10 blocked; invalid draft correction/leave details are cards rather than complete exception topology. |
| Attempts and appeals | D05 D09 | Resubmission new submission; regrade new grading attempt; appeal preserves historical decision. | Some regrade/appeal/withdrawal alternatives are described in cards, not all drawn as separate paths. |
| Onboarding branching | D04 D07 | Import/manual, identity link separate, local-only promotion explicit, invalid documents/workload/evidence corrected. | D04 import/manual and document correction branches are condensed in cards; expand in a subsequent authoring pass. |
| Operational constraints | D01 D02 D03 D07 | Private evidence, timeout ordering, transactional outbox, retention/deletion ledger, restore and monitoring. | Host qualification, integrations, restore, load and calibration are not executed. |

T01–T25 and T28–T30 are implementation scenarios and were not run. T26 is PARTIAL: 11 candidates and deliveries attempted, 7 HTML artifacts delivered; 4 native layouts blocked and all browser checks failed. T27 local run isolation is evidenced by the new timestamp folder and packaged input copies; no deployment or production operations occurred. Requirements are traced, not all artifact-accepted.
