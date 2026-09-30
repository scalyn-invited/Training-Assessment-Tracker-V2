# Local design handoff

Output: `C:\Users\Admin\Downloads\Archify_Upskilling_Run\Archify_Upskilling_Pack\output\archify-upskilling-20260929-181205`

Open `index.html` for all eleven statuses. Seven native HTML diagrams were delivered; D04, D05, D09 and D10 remain blocked by native layout validation. Their native editable JSON and interactive source-review pages are retained. Browser checks failed before capture; perceptual review was blocked by browser URL policy. The suite is not fully accepted.

## File map

- D01–D11/candidate.json: native Archify editable sources; candidate-before-* retain authoring history.
- D01/D02/D03/D06/D07/D08/D11/diagram.html: standalone interactive Archify viewers, static default motion and no hosted asset dependency.
- D*/review.html: accessible expandable source/card/relationship review; clearly marked status.
- D*/validate-*.json, deliver-*.json, *.execution.json: native diagnostics and exact commands. Delivered views also have diagram.visual-check.json.
- ARCHITECTURE_DECISIONS.md, ENTITY_AND_CONTRACT_CATALOGUE.md, SEMANTIC_REVIEW.md: design, entities/interfaces and gaps.
- REQUIREMENTS_COVERAGE.csv: all 40 original requirement IDs and acceptance scenarios.
- VALIDATION_REPORT.md, TOOLCHAIN.json, LOCAL_CHECKS.json, DELIVERY_MANIFEST.json: check boundaries and hashes.
- inputs/: supplied authoritative Markdown, contracts and synthetic fixtures; toolchain-evidence/: installed schemas and contracts.
- regenerate.ps1: validate/deliver/browser workflow for one candidate, stops on failure and records fresh receipts.
- Upskilling_Architecture_Output.zip: this run folder, excluding the ZIP itself. Archive verification is recorded beside the run directory.

## Reproduce

Use Node >=18; actual runtime was v22.23.2. Installed Archify is 2.17.0-dev.1 at `C:/Users/Admin/.codex/skills/archify`. The pack reviewed commit 69cf672087289033af5138648d3875d3d73fc431; installed metadata gives no commit, so TOOLCHAIN.json records exact local CLI/schema hashes instead. Do not assume v3 finalize compatibility. No install/update was performed.

From this directory, run:

`powershell -ExecutionPolicy Bypass -File ./regenerate.ps1 -Diagram D01`

This uses the installed CLI; runtime assets and renderers must remain available. Browser checks need functioning Chrome/Chromium and the DevTools pipe. ARCHIFY_CHROME can choose an executable without modifying the package. Do not disable host security controls to make a check pass.

Native successful candidates are frozen. Further revisions require a new validation/delivery receipt and browser review; preserve the prior good output. Archive authoring-history scripts explain this run but are not the regeneration entry point and should not be blindly rerun over accepted artifacts.

## Next implementation steps

Follow inputs/09_DELIVERY_BACKLOG.md and the separate implementation prompt only when beginning the build. B01 proves OIDC/MFA, delegated access and primary directory contracts; B02 builds policy/private files/queue/outbox; B03 onboarding/calendars/content; B04 two AI adapters + mock; B05 learner review; B06 KPI/adaptation; B07 approved sync; B08 operations; B09 calibrated pilot. All application tests remain unexecuted.

Confirm identity product, real endpoints/mappings, hosting limits, named owners, exact models/region/privacy, SMTP sender, actual scale, restore requirements and retention before live use. Proxmox is recommended; cPanel is conditional and unqualified. No application, deployment, notification, invitation, paid AI call or real employee-data access was performed.
