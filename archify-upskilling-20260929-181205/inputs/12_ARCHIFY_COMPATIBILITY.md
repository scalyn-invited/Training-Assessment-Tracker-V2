# Archify compatibility and provenance

Repository: https://github.com/tt-a1i/archify
Reviewed 29 September 2026 using the GitHub connector and the public repository documentation.
Reviewed default-branch commit: `69cf672087289033af5138648d3875d3d73fc431`.
README advertises stable v3.0.1; the reviewed default-branch skill uses the `finalize` workflow. The stable README's older `deliver` examples are not substituted for the actual installed skill contract.

## Sources

- https://github.com/tt-a1i/archify/blob/69cf672087289033af5138648d3875d3d73fc431/README.md
- https://github.com/tt-a1i/archify/blob/69cf672087289033af5138648d3875d3d73fc431/archify/SKILL.md
- https://github.com/tt-a1i/archify/blob/69cf672087289033af5138648d3875d3d73fc431/archify/references/delivery-contract.md

Archify accepts a system description through an agent. Native candidates use mode-specific JSON schemas. Supported modes reviewed: architecture, workflow, sequence, dataflow and lifecycle. The output is standalone interactive HTML. There is no claimed automatic import of the business-schema JSON files or conversion of this brief into application code.

## Agent setup

Use an existing installation first. The upstream README documents `npx skills add tt-a1i/archify -g` for general installation; that is a global operation and is not automatically run by this pack. The master prompt instead authorises a project-local repository copy if needed:

```bash
git clone https://github.com/tt-a1i/archify .tools/archify
git -C .tools/archify checkout --detach 69cf672087289033af5138648d3875d3d73fc431
```

These are setup instructions, not commands executed by the pack author. Do not clone over an existing checkout or discard local changes. If the commit is unavailable, report that and record the actual revision used. Node 18+ is referenced in the reviewed delivery contract; browser-check runtime/dependencies must be verified in the actual execution environment.

When using the local repository layout, the skill root is `.tools/archify/archify` and the CLI path is `.tools/archify/archify/bin/archify.mjs`. Each diagram's relative `meta.output` must agree with its requested HTML output under the working directory.

```bash
node .tools/archify/archify/bin/archify.mjs finalize architecture output/run/d01/candidate.json output/run/d01/system.html --quality showcase --json
```

The example is not directly runnable until the agent creates a valid candidate. Do not run a placeholder file and interpret its failure as an Archify defect.

## Validation contract

At the reviewed commit, a passing `finalize` covers validation, delivery, strict provenance check and real-browser check. It reports perceptual review separately; a normal automated pass does not mean someone inspected screenshots. Follow the installed skill's authoring/repair limits, layouts and version reminders. Keep receipts and failures. Generation of the eleven diagrams has intentionally been left for the destination agent; no Archify pass receipts are fabricated in this input pack.

## Package and output status

`PACKAGE_VALIDATION.json` covers only this input pack's syntax, references and selected fixtures. Output diagrams require their own native JSON, HTML and receipts after the master prompt runs. Never relabel package checks as architecture security verification or application test results.
