# Acceptance scenarios

These are specified tests, not claims that the product has passed. Use synthetic data until access/privacy policy and integrations are approved. Each test needs execution evidence, expected/actual result and defect link during implementation.

| ID | Scenario | Required outcome |
|---|---|---|
| T01 | Member requests another member; coordinator queries an unassigned group; guessed file ID | No records or files disclosed; inaccessible object returns 404; list counts do not leak hidden members. |
| T02 | Forged, expired or wrong-audience token; machine directory token used for user question | Authentication/scope failure; no use of arbitrary user header; no token or sensitive body logged. |
| T03 | IdP disable, directory deactivation, group removal, or permission snapshot older than five minutes | Revoke local access on receipt; expire caches; deny privileged/external personal reads if stale; record bounded latency. |
| T04 | Create a genuine manual local-only member and enrol | Identity can be invited independently; authorised local training/reporting work; primary export/query excluded. |
| T05 | Synthetic record in test environment reaches reporting/export/email code | Production paths reject it; test SMTP sink and sandbox credentials used; no production totals polluted. |
| T06 | Generate plan, edit, ask AI to revise, then approve | Approval binds exact content version; content includes lessons and tasks, not only headings; edits invalidate pending approval. |
| T07 | Week 1 Friday submission; grade reviewed Monday Week 2; adaptation approved Wednesday | Week 3 revised, Week 2 unchanged, baseline always available, member sees revision notice. |
| T08 | Week 3 adaptation approved after Thursday 17:00 local cutoff, or target has started | Reject stale publication; preserve baseline and move to next eligible target. No silent overwrite. |
| T09 | Evidence from final two weeks of a four-week programme | No out-of-range week generated; route to final review or explicitly proposed follow-on. |
| T10 | All five programme durations, 15 and 120 minute boundaries, invalid 14/121 minutes | Valid cases accepted; invalid duration or daily budget rejected in UI and backend; assessments count toward budget. |
| T11 | Two enrolments each fit their own budget but exceed the member's shared day | Combined schedule rejected or reviewed reschedule required. |
| T12 | Pause for leave; holiday changes adaptation cutoff; timezone differs from server | Version future calendar, preserve history, recompute dates/cutoffs and prevent double allocation. |
| T13 | Duplicate, stale and out-of-order directory events; lost webhook | Deduplicate and enforce versions; reconciliation restores missing change; cursor advances only after persistence. |
| T14 | Primary accepts promotion but acknowledgement times out | Reuse original idempotency key, reconcile person mapping, no duplicate; synthetic promotion never hits production. |
| T15 | AI gives unsupported criterion/evidence, malformed JSON, impossible workload or unverified resource | Validation rejects; bounded repair or human correction; no unchecked output published. |
| T16 | Provider outage, throttle, timeout, exhausted budget or fallback | Approved lessons still work; bounded retries, reservations accounted; only permitted fallback; switches logged. |
| T17 | Submission resubmitted, regraded, appealed or reviewed simultaneously | Preserve attempts and approved history; optimistic conflict on stale review; alternate reviewer for self/appeal policy. |
| T18 | Malicious file, macros, oversized file, failed OCR, submission asking AI to reveal secrets | Quarantine/block/manual correction; no execution or new privileges; no cross-member disclosure. |
| T19 | Provisional grade exists but not approved | Coordinator notified; member sees pending; official KPI and primary approved feed unchanged. |
| T20 | KPI baseline missing, too few samples, incomparable evidence, target changed | Insufficient evidence stays distinct from zero; effective-dated formula/target; evidence lineage retained. |
| T21 | SMTP transient failure, duplicate job, permanent bounce, coordinator loses group access before send | Retain review queue, deduplicate normal retry, alert on bounce, recheck recipient; track SMTP ambiguity without claiming exactly-once mail. |
| T22 | Restore from backup into an isolated host with database, files, keys and IdP configuration | Demonstrate RPO/RTO against agreed target; files open under correct policy; outbox replay safe; record measured result. |
| T23 | Representative 100-member dataset and 20 concurrent users, cohort submission burst | Measure p95 web target, queue lag, provider spend and storage; external AI wait never blocks interactive request. |
| T24 | Keyboard/screen reader/mobile use, autosave interruption, upload failure | Complete learner journey accessibly; recover draft; submission receipt distinguishes saved from submitted. |
| T25 | Real coordinator scores benchmark work independently of AI | Compare criterion disagreements, evidence quality and review time; agreed calibration gate before wider rollout. |
| T26 | Complete diagram generation using the master prompt | All 11 native JSON/HTML artifacts attempted; passed checks backed by receipts; blocked items honest; requirements trace complete; index and ZIP open. |
| T27 | Local output folder already exists or unrelated files are present | Create new run folder; preserve unrelated files; no routine authoring approval prompts; no external side effects. |
| T28 | cPanel host cannot support worker duration or required extraction utility | Mark cPanel unqualified; prefer whole-app Proxmox deployment; do not hide a remote-worker dependency. |
| T29 | Retention expiry, approved erasure, legal hold, restored old backup | Apply approved retention across files/traces/exports/cache; respect hold; reapply deletion ledger after restore before reopening. |
| T30 | Worker crashes after DB commit or provider result; lease expires during retry | Outbox persists; job identity prevents double business publication; timeout less than retry_after; unknown provider charges reconciled. |

## Diagram quality is not application verification

Archify's JSON/layout/provenance/browser checks validate the artifact pipeline, not OAuth correctness, learning effectiveness or a live deployment. Coverage review must separately ensure that diagrams express these tests. This input pack has no real users, network integrations or executed application acceptance tests.
