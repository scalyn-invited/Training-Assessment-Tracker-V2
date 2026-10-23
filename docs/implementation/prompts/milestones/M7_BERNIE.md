# M7 execution prompt for Bernie

## Role and objective

You are the AI implementation assistant working with **Bernie (Developer A and technical lead)** on Training Assessment Tracker V2. Bernie owns core implementation, technical checks and documented self-review. Saturnino is the UI/design counterpart and reviews applicable UX/accessibility only; the project owner accepts locally completed milestone outcomes. This prompt applies when the user asks to execute it; merely reading it or asking for status does not start implementation.

Task ID: `M7-BERNIE`. Milestone: **Integrated qualification pilot and release**. Previous estimate: 56 implementation hours (part of the superseded 640-hour AI-assisted baseline; not actual time). Current target: 3–5 M0–M7 work packages reach local acceptance per week, or about 2–3 weeks total. Effective 23 October 2026, Bernie plans 8h 06m core development/checks plus 1h 30m coordination/self-review (9h 36m); Saturnino plans 2h flexible UI design/small UI implementation plus 24m coordination (2h 24m). Across 2–3 weeks the 24–36 project hours comprise 20h 12m–30h 18m development/design and 3h 48m–5h 42m coordination. This target is provisional, not apportioned by milestone and requires a week-one reforecast; live integration, human pilot, the full 30-day learning cycle and production release remain outside it. Detailed prior hour allocations below are historical planning data, not a current timebox.

## Required context

1. Read [AGENT.md](../../../../AGENT.md), [AI workflow](../../AI_WORKFLOW.md) and [coordination](../../COORDINATION.md).
2. Read [status](../../STATUS.md), [your state](../../developers/BERNIE.md), [counterpart state](../../developers/SATURNINO.md), [decisions](../../DECISIONS.md), [contracts](../../CONTRACTS.md) and [cost approvals](../../COST_APPROVALS.md).
3. Read the [M7 charter](../../milestones/M7.md), [detailed plan](../../IMPLEMENTATION_PLAN.md), [requirements map](../../REQUIREMENTS.md) and relevant handoffs. PDF sections: 21–22, 25, 31–36; IDs: V2-13, V2-14, V2-15.
4. Inspect actual relevant code/tests, `platform/README.md` and `platform/docs/REMAINING_MILESTONES.md`. Earlier B01–B09 work is reusable evidence to assess, not new-milestone acceptance. Verify the local Git branch/HEAD and preserve existing changes.

## Preconditions

M1–M6 acceptance for the relevant release scope, actual identity/integration evidence, approved services and authorised pilot. Early deployment/test preparation may overlap M6.

D08 costs/data, D09 host/retention/recovery and D10 pilot/release ownership. No production deployment is implied by this prompt.

Review accepted C01–C07 versions and actual external contracts; release against pinned revisions.

If an entry condition is not met, separate dependent work from ready independent work. Do not manufacture a decision, schema, reviewer acknowledgement or external API. Explain the specific blocked operation and continue useful authorised work within this lane.

## Claim and ownership

Establish your bounded task/slice in your own developer record before code changes, using COORDINATION.md. A local claim is not a cross-machine lock. Verify overlap with Saturnino; do not edit reserved shared files or their state. If no shared acknowledgement is available, keep work isolated and non-overlapping.

Your ownership scope: Operational/configuration/runbook and integration fixes on authorised local/staging scope; production and commercial commitments remain separate explicit actions.

## Execute this work

- Qualify authorised live models, cost/data policies and SMTP sender/delivery evidence; keep missing approvals blocked.
- Qualify staging/host, scanner signatures/parser sandbox, private storage, migrations, retention and encrypted restore/rollback.
- Resolve integration defects and prepare a concrete release candidate and go/no-go evidence packet.
- Prove live identity/delegation, sync/removal, queue recovery and safe outbox/deletion replay independently of historical mock tests.
- Do not deploy, purchase, send real notifications or access real assessments unless the existing session or recorded human approval explicitly authorises the specific operation.

Inspect and extend the existing implementation; do not rewrite working modules to match a speculative design. Keep policy/state transitions server-side, preserve immutable history and record changes to contracts before consumers depend on them. Complete the selected ready scope and relevant checks rather than stopping at scaffolding.

## Acceptance and verification

Your lane must supply:

- Actual live identity/API/provider/SMTP proof, restore/rollback and host qualification with versions, environment and timestamps.
- No open critical tenant leakage, approval bypass, unapproved billing or data-loss path.

Also inspect the charter's shared milestone gate. Bernie's task evidence and technical self-review do not by themselves establish project-owner milestone acceptance or complete any applicable Saturnino UX/accessibility work. Use actual current checks and the verified command reference in AI_WORKFLOW.md. Checks must target isolated synthetic databases; newly needed scenarios may be added, but do not claim a named check or result exists without checking. Label historical, mocked, local, browser, live and human evidence separately.

## Mandatory boundaries

- Shared client/team/member permission checks apply before data retrieval, totals, AI context, exports and downloads; recheck after revocation and at background execution/delivery.
- AI proposes; authorised humans approve final assessments, programmes, KPI-impacting results and significant plan changes. No automatic final decisions.
- No real staff data, production credentials, real notifications, paid AI calls, new licences/infrastructure or deployment without specific applicable authorisation. Record approval already given; never invent it or ask again unnecessarily.
- Do not change the unresolved 30-day definition, next-week/N+2 policy or client-grant model by assumption. Continue independent work while those decisions are pending.
- Do not overwrite counterpart work, silently alter frozen contracts, push/merge or send external messages unless the user/session authorises the specific action.

## Required completion record

Update your own developer file and write a unique handoff under `docs/implementation/handoffs/` using [the template](../../templates/HANDOFF.md). Include exact revision/dirty state, changes, actual commands/results, blockers, relevant permission evidence and the next ready slice. Record Bernie's technical self-review against the exact revision, request Saturnino's separate UX/accessibility review when applicable, and leave milestone acceptance pending until the project owner actually records it. Reconcile shared STATUS only from current evidence; do not overwrite Saturnino’s record.

Expected handoff: Pinned release candidate, verification/rollback runbook and approval-ready go/no-go packet; deployment remains separately authorised.

Finish with a concise status, files changed, actual tests and limitations, remaining decisions/gates and Saturnino's requested next action. Do not claim the whole milestone or live release complete from your lane alone.
