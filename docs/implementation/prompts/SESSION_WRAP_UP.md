# Record actual progress and handoff

Use this file as the AI instruction for the requested operation. All paths below are repository-relative unless linked otherwise.

Read [root guide](../../../AGENT.md), [workflow](../AI_WORKFLOW.md), [coordination](../COORDINATION.md) and [status](../STATUS.md).

Identify the developer from explicit session context. Read root guidance, coordination and the developer's current state. Inspect the actual diff/revision and tests already run. Do not invent new implementation work just to fill the template, and do not rerun unrelated tests solely for documentation.

Update only the named contributor's file and a unique handoff using templates/HANDOFF.md. Record completed changes, exact verification with environment/revision, known gaps, actual hours only if known, remaining estimate, blockers/owners, contracts/decisions used, shared files reserved/released and next ready action. For Bernie work, record technical self-review or leave it pending. For Saturnino work, record UX/accessibility review evidence. Leave milestone acceptance pending until the project owner acts.

Bernie may reconcile STATUS.md only from actual latest evidence; Saturnino supplies the rollup proposal through the handoff unless explicitly delegated. Preserve history and counterpart state. Do not fabricate Bernie's self-review, Saturnino's UX review or project-owner acceptance. Return the concise update and file links. No external notification, remote push, merge or deployment is implied.
