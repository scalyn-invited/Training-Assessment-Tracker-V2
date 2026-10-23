# AI implementation workspace

Use this workspace to ask for status, identify the next ready task, implement a bounded milestone task, or review the other developer's work.

**Bernie = Developer A and technical lead. Saturnino = Developer B, UI and Design.** Effective 23 October 2026, the weekly target is 12 hours: Bernie 9h 36m (8h 06m development/checks plus 1h 30m coordination/self-review) and Saturnino 2h 24m (2h flexible UI design/small UI implementation plus 24m coordination). Target 3–5 M0–M7 work packages accepted locally per week, about 2–3 weeks from an agreed kickoff. This remains provisional and unvalidated and requires a week-one reforecast. Across two to three weeks, the target totals 24–36 project hours: 20h 12m–30h 18m development/design and 3h 48m–5h 42m coordination. Use a dedicated branch/worktree and pull request with required CI checks for each feature or milestone task; Bernie records technical self-review, Saturnino reviews applicable UX/accessibility, and the project owner accepts locally completed milestone outcomes. The prior 640 implementation-hour estimate (328 Bernie, 312 Saturnino; AI assistance already included), 28-week baseline and six-week contingency are superseded historical planning estimates, not actual time or progress. The target excludes live integration, human pilot, the full 30-day learning cycle and production release.

## Start here

1. Read [root rules](../../AGENT.md), [AI workflow](AI_WORKFLOW.md) and [coordination](COORDINATION.md).
2. Read [status](STATUS.md), [Bernie's state](developers/BERNIE.md), [Saturnino's state](developers/SATURNINO.md), [decisions](DECISIONS.md), [contracts](CONTRACTS.md) and [cost approvals](COST_APPROVALS.md).
3. Consult the [detailed Markdown plan](IMPLEMENTATION_PLAN.md) and [PDF requirements map](REQUIREMENTS.md).
4. Use a reusable prompt below. Each developer/milestone prompt is a separate file with role, prerequisites, scope, checks and handoff requirements.

## Everyday requests

```text
I am Bernie. Follow docs/implementation/prompts/STATUS_UPDATE.md and give me an evidence-backed project update. Do not change files.
```

```text
I am Saturnino. Follow docs/implementation/prompts/NEXT_STEP.md and identify my next ready task, its dependency on Bernie and the prompt to use. Do not implement yet.
```

```text
I am Bernie. Execute docs/implementation/prompts/milestones/M0_BERNIE.md. Complete ready work within its scope and record actual evidence and handoffs.
```

```text
I am Saturnino. Execute docs/implementation/prompts/milestones/M0_SATURNINO.md. Use a separate worktree and isolated synthetic database; preserve Bernie's work.
```

```text
I am Saturnino. Follow docs/implementation/prompts/CONTINUE_WORK.md for my existing claimed task. Check the latest handoff and shared contracts first.
```

```text
I am Bernie. Follow docs/implementation/prompts/REVIEW_HANDOFF.md for the handoff file I name. Review the exact commit and record the result; do not merge or deploy unless separately authorised.
```

```text
Follow docs/implementation/prompts/SESSION_WRAP_UP.md and record this session's actual progress in my contributor file and a handoff. Do not invent hours, technical self-review, UX review, project-owner acceptance or completed gates.
```

## Milestones and developer prompts

| Milestone | Bernie prior estimate (h) | Saturnino prior estimate (h) | Combined prior baseline (h) |
| --- | --- | --- | --- |
| [M0 Scope reconciliation and onboarding](milestones/M0.md) | [16h prompt](prompts/milestones/M0_BERNIE.md) | [16h prompt](prompts/milestones/M0_SATURNINO.md) | 32 |
| [M1 Client structure permissions and branding](milestones/M1.md) | [56h prompt](prompts/milestones/M1_BERNIE.md) | [40h prompt](prompts/milestones/M1_SATURNINO.md) | 96 |
| [M2 Cerebru identity directory and integration](milestones/M2.md) | [56h prompt](prompts/milestones/M2_BERNIE.md) | [32h prompt](prompts/milestones/M2_SATURNINO.md) | 88 |
| [M3 Assigned assessments and explainable review](milestones/M3.md) | [32h prompt](prompts/milestones/M3_BERNIE.md) | [48h prompt](prompts/milestones/M3_SATURNINO.md) | 80 |
| [M4 Approved 30 day development plans](milestones/M4.md) | [32h prompt](prompts/milestones/M4_BERNIE.md) | [40h prompt](prompts/milestones/M4_SATURNINO.md) | 72 |
| [M5 Weekly progress notes dashboards and reports](milestones/M5.md) | [40h prompt](prompts/milestones/M5_BERNIE.md) | [64h prompt](prompts/milestones/M5_SATURNINO.md) | 104 |
| [M6 Permission scoped training chatbot](milestones/M6.md) | [40h prompt](prompts/milestones/M6_BERNIE.md) | [24h prompt](prompts/milestones/M6_SATURNINO.md) | 64 |
| [M7 Integrated qualification pilot and release](milestones/M7.md) | [56h prompt](prompts/milestones/M7_BERNIE.md) | [48h prompt](prompts/milestones/M7_SATURNINO.md) | 104 |

## State and authority

This package was created on 9 October 2026 and its allocation and responsibility model was revised on 23 October 2026. Planning documents exist; M0–M7 delivery work is initially `not_started` in this ledger. Existing local B01–B09 features must be assessed for reuse, not rebuilt blindly or counted as accepted new milestones. The prior 640-hour estimates in the milestone table are retained for traceability only; the separate resourcing memo and section 6 of the detailed Markdown plan contain the current, provisional AI-assisted target. Do not infer that a milestone is accepted from the target cadence.

The [original Word plan](<../Training Assessment Tracker V2 Implementation Plan Two Developers.docx>) remains the formatted product/implementation baseline. The [separate AI-assisted resourcing memo](AI_ASSISTED_TWO_DEVELOPER_DELIVERY_PLAN.docx) adds the current hours and provisional build target; the Markdown plan mirrors it. Product changes require recorded decisions and a plan revision. These files are project documentation, not a globally installed AI skill. Share the committed files with both developers before relying on state across machines; no remote push is performed by creating them.
