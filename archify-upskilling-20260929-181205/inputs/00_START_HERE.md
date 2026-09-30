# Internal upskilling platform Archify input pack

Version 2.0 • 29 September 2026 • Proposed design and ready-to-use agent inputs.

## Use it in three steps

1. Extract this folder into a local project workspace accessible to your coding agent.
2. Open that folder in an agent with Archify available, such as Codex CLI, Claude Code, Cursor or OpenCode. Archify is an agent skill and CLI, not a website that imports this ZIP and builds an application.
3. Paste the entire contents of `01_AUTOPILOT_PROMPT.md` into the agent. Or say: **Read and execute 01_AUTOPILOT_PROMPT.md in this project. Local file creation and revisions are authorised; complete the full task autonomously.**

If using file attachments, attach this extracted folder or the ZIP where the agent can unpack it. Attach the Markdown specification and prompt directly if ZIP extraction is unavailable. No real staff details, secrets or API keys are needed for the architecture task.

## What this package contains

- `01_AUTOPILOT_PROMPT.md`: complete execution prompt with standing authorisation for local files and checks.
- `02_PRODUCT_ARCHITECTURE_SPEC.md`: expanded architecture and product specification, including the original requirements.
- `03_DOMAIN_AND_POLICY_RULES.md`: precise permission, scheduling, grading, sync and queue rules.
- `contracts/openapi.yaml`: proposed training integration API contract. Existing primary-platform endpoints remain unknown.
- `contracts/application-schemas.json`: application payload schemas for programmes, AI grades, adaptation proposals and events. These are not Archify IR.
- `fixtures/synthetic-scenarios.json`: invented test examples covering approval, adaptation, local-only profiles and access checks.
- `06_DIAGRAM_BRIEFS.md`: eleven focused diagrams, their topology and required details.
- `07_REQUIREMENTS_TRACEABILITY.csv`: original and added requirements mapped to diagrams and acceptance tests.
- `08_ACCEPTANCE_TESTS.md`: behavioural and operational release checks.
- `09_DELIVERY_BACKLOG.md`: implementation increments and completion gates.
- `10_DECISIONS_AND_DEFAULTS.md`: resolved design defaults and genuinely unknown deployment details.
- `11_IMPLEMENTATION_HANDOFF_PROMPT.md`: separate optional prompt for building the application after the design stage.
- `12_ARCHIFY_COMPATIBILITY.md`: repository evidence and regeneration guidance.
- `reference/Upskilling_Platform_Architecture_Plan.docx`: updated human readable plan.
- `PACKAGE_VALIDATION.json`: checks actually performed on this input package.

## What happens next

The master prompt tells your agent to generate native Archify JSON and interactive HTML, validate them, create a local index and package the output. It explicitly removes routine local file approval pauses. It does not remove the training product's coordinator approval workflow.

This pack does not claim that the application exists, that the proposed API is implemented, or that the eleven Archify diagrams have already been rendered. It supplies the complete design inputs and execution instructions. Generated outputs must receive their own Archify receipts. A prompt cannot override the host application's system or tool permission policy.

## Version 2 improvements

Added a precise content lifecycle, permission policies, curriculum/grade schemas, concrete API envelopes, fixture cases, error behaviour, workflow timing, budgets, delivery gates and diagram briefs. Clarified that genuine local-only members can use local training and reporting; only synthetic test records are excluded from normal production reporting. Retained optional export as an explicit action. Clarified that two weeks ready does not mean two permanently frozen weeks.
