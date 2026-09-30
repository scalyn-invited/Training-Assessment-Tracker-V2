# Upskilling architecture autopilot prompt

Use the Archify skill to produce the complete proposed architecture and interactive diagram suite for the internal upskilling platform described in this package. Execute the work now, end to end. Do not stop after a plan, outline, first diagram or progress report.

## Local work is already authorised

I explicitly authorise you to create directories, write and revise local Markdown, JSON, YAML, CSV, HTML, SVG, scripts, validation receipts, screenshots and ZIP files inside this project workspace without asking for approval. You may run local renderers, validators and preview checks, and repair your generated files. Do not ask “shall I create the files?”, “may I continue?” or request sign off between diagrams. Choose sensible reversible defaults, record assumptions, and continue autonomously. Preserve the application's coordinator approval gates: this local authoring authorisation does not remove human approval of training plans, grades or adaptations.

Use the already installed Archify skill where available. If missing, I authorise a project local copy of https://github.com/tt-a1i/archify into `.tools/archify`, pinned to `69cf672087289033af5138648d3875d3d73fc431` when available. Do not install globally or change my account settings. Node and browser dependencies may be prepared locally when your environment permits. Do not bypass system, sandbox or tool permissions. If an external prerequisite is blocked, record it precisely and complete all independent local deliverables. Do not turn a missing credential, hostname or company logo into a blocking question.

Never overwrite unrelated files. Create `output/archify-upskilling-<local timestamp>/` once; keep diagrams in separate child directories. Reuse each child for repairs and keep the previous valid artifact. Reruns use a new output folder. Do not publish, deploy, push to GitHub, invite users, send emails, access real employee data, call paid AI APIs or change production systems. These actions are outside this local design task.

## Source authority and reading order

Read `00_START_HERE.md`, `02_PRODUCT_ARCHITECTURE_SPEC.md`, `03_DOMAIN_AND_POLICY_RULES.md`, `06_DIAGRAM_BRIEFS.md`, `10_DECISIONS_AND_DEFAULTS.md` and the contract, test and traceability files. The written requirements and explicit invariants are authoritative. Fixtures are synthetic examples, not production facts. Contracts are proposed interfaces, not discovered endpoints on the existing primary platform. The Word document is a human readable companion.

This is a proposed system, not a source verified map of an implemented repository. Do not invent source file citations, claim deployment, or treat Archify's source as the training application's source. The supplied `contracts/*.json` files are application schemas, NOT native Archify diagram IR. Author new diagram candidates against the installed Archify schemas. Never feed a business schema into the diagram renderer.

## Architecture to preserve

- Lightweight Laravel modular monolith, Blade/Livewire interface, MariaDB, database backed durable queue, private file storage, separate worker and scheduler using the same codebase. Recommended production deployment: Proxmox Linux VM. cPanel is a conditional alternative after capability testing; its identity provider remains external.
- Assume authentik at goauthentik.io for shared OIDC login and mandatory MFA; mark the product name as pending confirmation. Every application gets its own client. Application authorisation checks roles, group assignments, record ownership and environment.
- Primary administrator; coordinators restricted to assigned groups; members restricted to their own records. An accountable coordinator owns each enrolment. No self approval of grades.
- Import people and directory groups from the primary platform. Allow local creation. Real local-only people may train and appear in authorised local reports, but do not export by default. Synthetic test records never enter production sync or reporting. Explicit promotion must be idempotent and require duplicate review.
- The primary platform is the consolidated organisational source of truth. The training application owns training evidence and approval versions and publishes approved facts. User initiated AI questions require delegated identity and server enforced record permissions, separate from machine directory credentials.
- Onboarding captures current role, target role and purpose, third party assessments, baseline skills, accessibility, availability, resources and KPIs. Duration is exactly 4/6/8/10/12 weeks; 15–120 minutes per active day including assessment and revision. One member's combined programmes share that capacity.
- Provider independent AI gateway with per-task model selection, explicit capabilities, costs and privacy policies. AI generates draft plans, provisional grades and adaptation proposals. Human review records edits, rationale and immutable approvals. No provider response may change authorisation, KPI targets or workload policy.
- Learners receive actual lessons, activities, resources and assessments, not just a course outline. Autosave, submission receipts, attempts, resubmissions, appeals, reminders and private evidence downloads are required.
- SMTP2GO through generic SMTP for review notifications. AI grades remain provisional until coordinator approval. Only approved results update official KPIs and normal source-platform answers.
- Generate and approve baseline content for the whole programme. Keep at least current and next week's training ready. Week N results normally change Week N+2; review occurs during Week N+1. The target freezes two business days before its start at the configured local cutoff, or as soon as work starts. Missing approval retains the baseline. Never require immediate weekend review.
- KPIs state what, why, baseline, target, formula, evidence, cadence and sample sufficiency. Suggestions fit remaining time and do not silently change required work. No unsupported employment decisions or competence claims.

## Required deliverables

Create all eleven diagrams in `06_DIAGRAM_BRIEFS.md`. For every diagram retain native Archify JSON, standalone interactive HTML and all generated validation/provenance/browser receipts. Use stable semantic IDs, concise labels and informative detail cards. Split concerns into the prescribed views rather than one unreadable diagram. Show direction, approval gates, asynchronous work, trust boundaries, failure paths and ownership accurately. Use an accessible restrained theme, readable labels, static default motion and the installed skill's supported viewer capabilities. No invented logos or dependency on hosted assets.

Also create:
1. `index.html`, a local landing page linking all diagrams and their status, the design summary and unresolved implementation assumptions. Relative links only.
2. `ARCHITECTURE_DECISIONS.md`, explaining choices and tradeoffs, including Proxmox versus cPanel.
3. `REQUIREMENTS_COVERAGE.csv`, mapping every supplied requirement to diagram IDs and acceptance scenarios; identify gaps explicitly.
4. `VALIDATION_REPORT.md`, with separate per-artifact schema/layout/delivery/provenance/browser/perceptual-review statuses, exact commands, receipt locations and unresolved failures.
5. `HANDOFF.md`, with the file map, regeneration commands, version/commit, dependencies, known limitations and next implementation steps.
6. `DELIVERY_MANIFEST.json`, with relative paths, diagram types, input/output hashes and factual check states.
7. `Upskilling_Architecture_Output.zip`, containing this output folder and its editable diagram sources.

## Generation and validation procedure

Read the installed Archify `SKILL.md` and the relevant authoring defaults, example, mode schema and common schema before selecting fields. Treat the reviewed commit as compatibility evidence; if the installed version differs, record that and follow its actual schema and CLI rather than guessing fields or silently updating it.

For the reviewed commit, use its documented command from the project working directory:

`node <archify-skill-root>/bin/archify.mjs finalize <type> <candidate.json> <output.html> --quality showcase --json`

Set each candidate's `meta.output` to the corresponding portable POSIX relative HTML path. Use absolute filesystem paths in the final handoff. Run deliveries sequentially where output directories overlap. Do not edit a candidate while finalization runs. Keep native receipts; never fabricate or manually turn a failed check into a pass.

Follow the skill's repair contract: two focused repairs, then one evidence based retry where specified; preserve semantics and last good artifacts. If a particular diagram remains blocked, record the exact failure and complete the other diagrams. Respect locks and provenance journals; do not delete an unknown active lock. Separate folders avoid shared delivery contention. Missing browser tooling means browser checks are not run, not passed.

Perform visual inspection when the environment provides it, especially for dense or branching views, and record what was actually inspected. Do not claim visual review merely because automated validation passed. For a receipt that requires a placement or width review, follow the installed delivery contract. Show any mandatory version update notice once; do not update automatically.

Finally reconcile every diagram with the requirement matrix and business invariants. Verify two-week readiness is not confused with permanently freezing the next week. Ensure no diagram shows the AI approving its own work, the source platform reading unrestricted staff data, or local-only members being automatically exported. Package all outputs and finish with file paths, completion counts and concrete unresolved items. Do not end by asking whether I want you to proceed.
