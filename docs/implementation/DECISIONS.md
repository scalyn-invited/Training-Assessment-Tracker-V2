# Product and architecture decisions

Maintainer: Bernie. Saturnino may propose decisions through handoffs. A proposal is not an approval; do not fill in another person's decision or date.

## Confirmed user instructions

- Two contributors: Bernie (Developer A and technical lead) and Saturnino (Developer B, UI and Design).
- Previous planning baseline (superseded for the current forecast): 15 project hours per developer per week.
- Current planning input (effective 2026-10-23): 12 project hours per week. Bernie receives 9h 36m (80%): 8h 06m core development, technical checks and self-review plus 1h 30m coordination. Saturnino receives 2h 24m (20%): 2h flexible UI design and small UI implementation, including usability/accessibility checks, plus 24m coordination. Across two to three weeks this is 24–36 project hours: 20h 12m–30h 18m development/design and 3h 48m–5h 42m coordination. The target remains 3–5 M0–M7 work packages accepted locally each week, about 2–3 weeks overall, with a mandatory week-one reforecast. Live integration, pilot observation, the full 30-day learning cycle and production release are excluded.
- Bernie performs documented technical self-review; Saturnino's review is limited to UX and accessibility. The project owner accepts locally completed milestone outcomes. No independent technical peer approval is implied.
- Prioritise client structure, permissions and integration foundations.
- Chatbot and reporting must respect relevant client, team and member permissions.
- Flag additional paid services, licensing and infrastructure for approval before commitment.
- The current task creates AI-ready project Markdown and updates AGENT.md; it does not start feature development.

## Open decision register

| ID | Topic | Proposed starting point | Needed by | Preparer | Actual decision owner | Status |
| --- | --- | --- | --- | --- | --- | --- |
| D01 | Client model, organisation mapping, team versus cohort and one active client | One active client per member; explicitly model imported teams and training cohorts. Confirm mapping before schema constraints. | M0 / M1 | Bernie | Project owner | open |
| D02 | Role grants, cross-client staff, transfer/history and sensitive access | Explicit client capabilities; no automatic history transfer or sensitive access from Admin title. Cross-client staff needs explicit accepted model. | M0 / M1 | Bernie | Project owner | open |
| D03 | Actual Cerebru architecture, API/IdP, delegation, navigation and source ownership | Discover contracts; immutable IDs; parent-owned imported fields and locally owned training records are proposed. No guessed endpoints. | M0 discovery; M2 live | Bernie | Project owner and authorised Cerebru contact | open |
| D04 | 30 calendar versus working days, start inclusion, timezone and final period | Propose 30 calendar days including start, training on working days, partial final reporting period. Not yet accepted. | M0 decision; M4 implementation | Bernie; Saturnino reviews UX | Project owner with coordinator | open |
| D05 | Next-week recommendations versus protected N+2 publication | Existing N+2/freeze policy stays until an explicit reviewed change. Significant changes require human approval and unstarted eligible targets. | M0 decision; M4/M5 change | Bernie | Project owner with coordinator | open |
| D06 | Assessment rubrics, KPI formulas, content/resources and success thresholds | Versioned human-owned criteria; comparable approved evidence; missing evidence is not zero. Actual content/thresholds not provided here. | Before M3/M4; before M5 metric acceptance | Bernie; Saturnino acceptance cases | Project owner and training coordinator | open |
| D07 | Report/CSV scope, client visibility, sensitive data and private chats | Basic in-app reports and CSV; member-only chat history; explicit authorised aggregates. Confirm any expanded visibility or formats. | M0 scope; before M5/M6 | Saturnino proposes; Bernie records | Project owner | open |
| D08 | AI providers/models, permitted data/region and spend cap | Reuse adapters/mocks. No paid usage, subscription or fallback without specific cost and data approval. | Before first paid/live data use | Bernie | Project owner | open |
| D09 | Host/SMTP/retention/erasure/restore targets and operational ownership | Qualify actual environment; existing runbook targets remain proposals. Agree chat/notes/report retention separately. | M2 planning; before M7 live | Bernie | Project owner | open |
| D10 | Pilot people, independent reviewers, observation and release approver | Owner supplies authorised pilot and independent human reviewers; 30-day observation and actual go/no-go. | Recruit by week 20; M7 | Saturnino prepares; Bernie records | Project owner | open |

## Decision record format

For each resolved ID, append: decision; approver's actual name/role; date; reference to their instruction/approval; alternatives; affected requirements, contracts and tests; migration/compatibility implications; effective version; whether estimates change. Preserve the previous proposal/history. If no approver is known, record `owner_unconfirmed`; do not assign their authority to an AI.

Pending choices block only dependent operations. Continue independent inspection, fixtures, UI sketches and tests. Ordinary reversible implementation choices within an accepted contract do not require new product approval.

## 2026-10-27 — M2 starter scope approved in conversation

The user approved implementation of the M2 starter plan: defer authentik implementation and hosting; implement local email/password access, administrator-issued 1h setup/reset links, 7-day explicit client invitations, Member-only initial access and all-session password-change invalidation. Global and Client Admins create accounts; Client Admin recovery covers other active members, including multi-client accounts, excluding self and Global Admins. No public registration, SMTP delivery or MFA in isolated local qualification. Minimum password length 15; existing idle/absolute session limits retained. Bernie-led implementation with separate Saturnino UX review. Cerebru discovery begins with an evidence checklist because documentation and sandbox access are unavailable. This supersedes the 26 October 'authentik now' sequencing only; it is not live/data/cost/release or milestone acceptance.

### 2026-10-28 — Explicit localhost production exception
User authorised APP_ENV and training environment production in a loopback-only profile, separate empty database, ordinary account UI and named first-admin setup. Existing demo databases remain unchanged. This supersedes the local/test-only password gate solely for the explicit localhost profile; it does not authorise hosted release, MFA deferral for hosted use, external email, live AI or Cerebru. See the localhost production handoff.

## 2026-10-28 — M3 scope approved in conversation

Bernie explicitly requested implementation of the agreed M3 plan: backend plus minimal usable screens; reusable published assessment definitions; coordinator-configurable rubrics qualified with synthetic examples; rejection allowing immutable resubmission; evidence-linked manual scoring while live AI remains unapproved; and activation in the existing localhost production profile only after isolated checks and a verified restorable backup. Actual business rubric content, scoring calibration and success thresholds remain pending under D06. No paid provider, external email, hosted release, remote push, merge or human acceptance is approved by this scope.
