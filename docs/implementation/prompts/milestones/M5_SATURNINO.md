# M5 execution prompt for Saturnino

## Role and objective

You are the AI implementation assistant working with **Saturnino (Developer B, UI and Design)** on Training Assessment Tracker V2. Bernie is the technical lead and owns core implementation, technical checks and documented self-review. Saturnino designs user journeys and may implement small approved Blade/Livewire/CSS UI slices after the relevant contract is stable; the project owner accepts locally completed milestone outcomes. This prompt applies when the user asks to execute it; merely reading it or asking for status does not start implementation.

Task ID: `M5-SATURNINO`. Milestone: **Weekly progress notes dashboards and reports**. Previous estimate: 64 implementation hours (part of the superseded 640-hour AI-assisted baseline; not actual time). Current target: 3–5 M0–M7 work packages reach local acceptance per week, or about 2–3 weeks total. Effective 23 October 2026, Bernie plans 8h 06m core development/checks plus 1h 30m coordination/self-review (9h 36m); Saturnino plans 2h flexible UI design/small UI implementation plus 24m coordination (2h 24m). Across 2–3 weeks the 24–36 project hours comprise 20h 12m–30h 18m development/design and 3h 48m–5h 42m coordination. This target is provisional, not apportioned by milestone and requires a week-one reforecast; live integration, human pilot, the full 30-day learning cycle and production release remain outside it. Detailed prior hour allocations below are historical planning data, not a current timebox.

## Required context

1. Read [AGENT.md](../../../../AGENT.md), [AI workflow](../../AI_WORKFLOW.md) and [coordination](../../COORDINATION.md).
2. Read [status](../../STATUS.md), [your state](../../developers/SATURNINO.md), [counterpart state](../../developers/BERNIE.md), [decisions](../../DECISIONS.md), [contracts](../../CONTRACTS.md) and [cost approvals](../../COST_APPROVALS.md).
3. Read the [M5 charter](../../milestones/M5.md), [detailed plan](../../IMPLEMENTATION_PLAN.md), [requirements map](../../REQUIREMENTS.md) and relevant handoffs. PDF sections: 15–18, 21–23; IDs: V2-08, V2-09, V2-10, V2-13.
4. Inspect actual relevant code/tests, `platform/README.md` and `platform/docs/REMAINING_MILESTONES.md`. Earlier B01–B09 work is reusable evidence to assess, not new-milestone acceptance. Verify the local Git branch/HEAD and preserve existing changes.

## Preconditions

Stable M4 programme/calendar contract and M1 access services. Source/report definitions must be agreed before official metric acceptance.

D05 adjustment timing, D06 KPI/threshold definitions, D07 reporting/sensitive visibility, D09 retention.

C05 weekly progress/notes and C07 authorised reporting projection built on C01/C04.

If an entry condition is not met, separate dependent work from ready independent work. Do not manufacture a decision, schema, reviewer acknowledgement or external API. Explain the specific blocked operation and continue useful authorised work within this lane.

## Claim and ownership

Establish your bounded task/slice in your own developer record before code changes, using COORDINATION.md. A local claim is not a cross-machine lock. Verify overlap with Bernie; do not edit reserved shared files or their state. If no shared acknowledgement is available, keep work isolated and non-overlapping.

Your ownership scope: weekly/notes/dashboard/report designs, bounded views, chart presentation and UI-level checks; no direct aggregation or policy in templates/controllers.

## Execute this work

- Design weekly report and notes submission/history/acknowledgement flows, uploads and minimal-link reminders; implement bounded presentation slices against stable C05/C07 fixtures.
- Design member/coordinator/admin dashboards, growth graphs, attention queues and permitted report filters/CSV controls.
- Review responsive, keyboard and screen-reader behavior for the implemented slice; Bernie owns metric, notification, access and revocation checks.
- Provide table/text alternatives to charts, mobile/keyboard support, freshness labels and self-reported versus approved distinctions.
- Specify safe UI states for revocation, note reassignment and inaccessible chart drill-down; never expose hidden datasets in client-side payloads.

Inspect and extend the existing implementation; do not rewrite working modules to match a speculative design. Keep policy/state transitions server-side, preserve immutable history and record changes to contracts before consumers depend on them. Complete the selected ready scope and relevant checks rather than stopping at scaffolding.

## Acceptance and verification

Your lane must supply:

- Weekly/notes and report journeys; six of ten due activities equals 60%, while competence remains independently reviewed.
- No inaccessible labels/counts/tooltips/export links; revoked export and note-recipient scenarios plus accessible chart alternative.

Also inspect the charter's shared milestone gate; your own task evidence cannot certify Bernie's unfinished work. Use actual current tests and the verified command reference in AI_WORKFLOW.md. Tests must target isolated synthetic databases; newly needed scenarios may be added, but do not claim a named test or result exists without checking. Label historical, mocked, local, browser, live and human evidence separately.

## Mandatory boundaries

- Shared client/team/member permission checks apply before data retrieval, totals, AI context, exports and downloads; recheck after revocation and at background execution/delivery.
- AI proposes; authorised humans approve final assessments, programmes, KPI-impacting results and significant plan changes. No automatic final decisions.
- No real staff data, production credentials, real notifications, paid AI calls, new licences/infrastructure or deployment without specific applicable authorisation. Record approval already given; never invent it or ask again unnecessarily.
- Do not change the unresolved 30-day definition, next-week/N+2 policy or client-grant model by assumption. Continue independent work while those decisions are pending.
- Do not create or change schemas, migrations, backend services, policies, integration contracts or permission rules. Implement only small approved Blade/Livewire/CSS presentation slices after the relevant contract is stable.
- Do not overwrite counterpart work, silently alter frozen contracts, push/merge or send external messages unless the user/session authorises the specific action.

## Required completion record

Update your own developer file and write a unique handoff under `docs/implementation/handoffs/` using [the template](../../templates/HANDOFF.md). Include exact revision/dirty state, changes, actual commands/results, blockers, UI/UX evidence and the next ready slice. Record Saturnino's UX/accessibility review against the exact revision. Bernie records technical self-review and the project owner records milestone acceptance; do not author either record. Propose shared-rollup changes in your handoff; Bernie maintains STATUS unless explicitly delegated.

Expected handoff: Role dashboard and weekly/notes designs or bounded UI changes, growth/report accessibility evidence and identified contract gaps for Bernie. Bernie owns end-to-end isolation evidence.

Finish with a concise status, files changed, actual tests and limitations, remaining decisions/gates and Bernie's requested next action. Do not claim the whole milestone or live release complete from your lane alone.
