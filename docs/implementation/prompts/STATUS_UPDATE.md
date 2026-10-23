# Read-only project update

Use this file as the AI instruction for the requested operation. All paths below are repository-relative unless linked otherwise.

Read [root guide](../../../AGENT.md), [workflow](../AI_WORKFLOW.md), [coordination](../COORDINATION.md) and [status](../STATUS.md).

Read root AGENT.md and the shared workflow/state files. Identify the requester from explicit context; if absent, report both Bernie and Saturnino. Inspect local Git status/branch/HEAD and evidence cited by both developer records, the selected milestone and recent handoffs. Do not edit files, claim work, run mutating tests, fetch, push, merge or deploy just to answer this request.

Return:
1. As-of time and exact local revision; note whether counterpart/shared state is current or unverified.
2. Current milestone/task for each developer; distinguish planning, implemented local, tested, reviewed and live-qualified.
3. Changes since the last recorded update, with file/commit/test evidence or explicitly unknown.
4. Open blockers/decisions/cost or service prerequisites and their real owners.
5. The next ready action and exact prompt path for each developer.
6. Schedule impact only when supported by completed acceptance and remaining estimates; no invented completion percentages or actual hours.

If the rollup conflicts with a developer record or code, report the conflict. Historical B01–B09 tests are not current M0–M7 acceptance. If the user explicitly requests recording the update too, write only the permitted owned state and evidence; otherwise return the report without mutations.
