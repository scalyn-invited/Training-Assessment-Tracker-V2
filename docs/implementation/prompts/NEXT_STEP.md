# Read-only next-step selection

Use this file as the AI instruction for the requested operation. All paths below are repository-relative unless linked otherwise.

Read [root guide](../../../AGENT.md), [workflow](../AI_WORKFLOW.md), [coordination](../COORDINATION.md) and [status](../STATUS.md).

Read root AGENT.md, AI_WORKFLOW.md, COORDINATION.md, STATUS.md, both developer files, decisions/contracts/cost approvals and relevant handoffs. Inspect current local revision without changing files. Use the named developer from context; when absent give both lanes, rather than guessing.

If there is an existing claimed task, identify its next unmet acceptance condition first. Otherwise choose the earliest ready bounded slice in the developer's milestone prompt. Check producer contract review, product decisions, shared-file ownership and external/cost gates. Distinguish independent work from a blocked dependent operation; do not claim nothing can be done if setup, fixtures or owned work remains ready.

Return: contributor; task/slice; why ready; expected deliverable; prerequisites satisfied and evidence; unresolved blockers and owner; files likely touched; estimate within the contributor's current weekly allocation or a clearly labelled new slice estimate; exact milestone prompt path; required technical/UX/project-owner handoff; acceptance checks. Include a copyable execute request. Do not implement, claim the task or mark acceptance merely because you selected a next step.
