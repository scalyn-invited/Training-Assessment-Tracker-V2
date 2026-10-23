# Review a handoff

Use this file as the AI instruction for the requested operation. All paths below are repository-relative unless linked otherwise.

Read [root guide](../../../AGENT.md), [workflow](../AI_WORKFLOW.md), [coordination](../COORDINATION.md) and [status](../STATUS.md).

Use the explicit reviewer identity, review type and named handoff. Bernie may perform technical self-review; Saturnino may perform UX/accessibility review only. If no handoff is named and there is one unambiguous ready handoff, state the selection. If several exist, report the candidates and ask which to review before dependent work. Read root rules, coordination, the milestone charter and the producer's actual code diff/commit. A producer's claim of passing checks is not proof at the reviewed revision.

For Bernie's technical self-review, review client/team/member isolation, human approval, versions, migration safety, contract compatibility, scope and cost/live boundaries and run relevant authorised checks in an isolated environment. For Saturnino's UX/accessibility review, inspect the actual affected interface, responsive behavior, keyboard flow, labels, states and accessible alternatives without claiming backend or security approval. Record unchecked or blocked layers.

Create a separate review record using templates/REVIEW.md. Include actionable findings with severity and evidence, or readiness tied to the exact task revision. Update only the reviewer's contributor file and link the review; ask the rollup maintainer to reconcile via the handoff. Do not change another contributor's state or fabricate their findings. Technical self-review or UX review does not imply project-owner milestone acceptance, merge, production release or paid-service approval.
