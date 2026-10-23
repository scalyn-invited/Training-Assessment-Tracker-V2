# PDF to delivery traceability

Source: [Training Assessment Tracker V2 PDF](<../Training Assessment Tracker V2.pdf>), 26 pages and sections 1–37. These V2 IDs supplement the older R01–R40/T01–T30 map; do not relabel or delete historical evidence. Maintainer and technical evidence owner: Bernie. Saturnino supplies UI/design and UX/accessibility evidence via handoffs.

| ID | PDF sections | Requirement | Milestone | Ownership | Acceptance focus |
| --- | --- | --- | --- | --- | --- |
| V2-01 | 1–7, 24–25 | Client/team/member structure, branding and configurable client experience | M1 | Bernie schema; Saturnino UI | Two-client isolation and branding; source/team/cohort mapping |
| V2-02 | 3–4, 7, 25 | Admin/coordinator/member permissions and extensible capabilities | M1 | Bernie policy/checks; Saturnino denial-state UX | Deny wrong client/team/member including downloads/jobs/totals |
| V2-03 | 6–7, 25 | Cerebru authentication, directory import/sync, relationships and navigation | M2 | Bernie adapters; Saturnino UX | Actual sandbox identity/sync/revocation plus labelled contract tests |
| V2-04 | 8–9, 20 | Assessment creation/assignment, written responses, uploads/context/history | M3 | Bernie domain; Saturnino workflow | Assignment through member evidence submission; quarantine enforced |
| V2-05 | 10, 22 | AI score/category breakdown, evidence explanation and strengths/gaps | M3 | Bernie gateway; Saturnino review UI | Validated rubric/evidence, missing not zero; human calibration M7 |
| V2-06 | 11, 14 | Human review, modification/rejection/approval and audit lineage | M3–M4 | Bernie domain; Saturnino review UX | Exact-version decisions; no automatic official AI result |
| V2-07 | 12–14 | Approved assessment-informed 30-day plan and realistic workload | M4 | Bernie calendar; Saturnino plan UX | Explicit duration mode, shared capacity, legacy preservation |
| V2-08 | 15–16 | Weekly progress, AI comparison and adjustment recommendations | M5 | Bernie domain; Saturnino weekly UX | Period/evidence lineage, human-approved eligible future changes |
| V2-09 | 17, 23 | Growth graph, member/team/client reporting and role dashboards | M5 | Bernie projections; Saturnino reports | Comparable approved metrics, accessible graph, scoped totals/exports |
| V2-10 | 18, 21 | Private member notes, SMTP notices and reminders | M5; M7 live | Bernie recipients; Saturnino notes UX | Assigned recipient, revoked/reassigned access, delivery proof M7 |
| V2-11 | 19, 22–23 | Basic grounded training chatbot | M6 | Bernie context; Saturnino chat UX | Own approved context/history/citations; injection/revocation denial |
| V2-12 | 5, 24 | Client name/logo/colours/title and programme settings | M1 | Saturnino UI; Bernie scope/config | No cross-client configuration leak; safe assets/accessibility |
| V2-13 | 22, 25, 31, 35 | Shared AI/security/audit/data isolation and operational foundations | M1–M7 | Bernie; Saturnino UX/accessibility review | Privacy, versioning, costs and live release gates |
| V2-14 | 26–31, 35, 37 | Architecture/backlog/repository workflow and decisions | M0–M2 | Bernie technical lead; Saturnino UI/design input | Updated source precedence, decisions, contracts and setup evidence |
| V2-15 | 32–34, 36 | Complete MVP story, demo, pilot and continuous development cycle | M7 | Bernie qualification; Saturnino pilot UX; owner acceptance | One member end-to-end plus second-client denial and truthful status |

## Evidence mapping to maintain during implementation

For each ID add actual ticket/slice, code paths, check/scenario paths, current revision, last execution date/result, Bernie's technical self-review, applicable Saturnino UX/accessibility review, project-owner acceptance and open live gate. All current rows are scope mapping only, not acceptance claims. Reuse old evidence only after verifying applicability to the new client model and current revision.

## Scope conflicts to resolve

- Single-organisation wording versus multi-client isolation: D01/D02; new scope wins, migration details require a recorded model.
- Legacy block durations versus 30-day plan: D04; retain old programmes and add an explicit mode.
- Following-week recommendations versus existing N+2 publication/freeze: D05; do not weaken approval or started-content safeguards silently.
- No default private chat visibility for coordinators/admins; any expansion requires D07 and access tests.
