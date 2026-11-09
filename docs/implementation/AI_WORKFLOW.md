# AI session workflow

Read [AGENT.md](../../AGENT.md) and the [feature checklist](FEATURE_CHECKLIST.md), the primary development tracker. Status and next-step requests are read-only unless recording progress or implementation is also requested. Do not infer completed work or approvals.

Do not ask the requester to identify themselves as Bernie or Saturnino. Proceed within the authorised task scope and use the repository's configured Git author unless explicitly instructed otherwise. Preserve shared-file coordination and evidence-backed review requirements; never invent a person's review or approval.

For authorised implementation:

1. Inspect current code, relevant requirements, decisions, contracts, evidence and Git state. Define one bounded feature and acceptance criteria in the checklist. Follow [coordination](COORDINATION.md) before editing shared files.
2. Implement only the selected feature, preserving behaviour outside its approved requirements.
3. Test acceptance criteria, relevant edge cases and regressions using appropriate automated and manual checks. Fix issues and retest. If blocked, record the blocker and seek direction before starting another feature.
4. Record concise tests, results, issues/fixes and evidence references in the checklist. Include the tested revision or working-tree state and relevant limitations. Distinguish passed, failed, blocked and not-run checks, and local and live verification. Record applicable technical self-review, UX/accessibility review and owner acceptance truthfully.
5. Mark the feature complete only when implementation, QA, fixes, evidence and applicable acceptance gates are finished. Then select the next feature.

Do not use developers/ or prompts/ as workflow inputs or create routine handoffs, duplicate status records or lengthy reports. Historical evidence may be referenced without copying it. Existing safety, privacy, paid-service and production authorisation rules remain in force. Verify test commands and isolate test databases before use; never reset shared or production data.
