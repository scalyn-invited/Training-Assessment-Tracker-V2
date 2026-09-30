# Validation report

7 of 11 native diagrams delivered with 9/9 showcase checks, zero errors/warnings. Four deliveries failed. No browser or perceptual pass is claimed.

| ID | Type | Schema | Layout | Showcase | Delivery | Provenance | Browser | Perceptual |
|---|---|---|---|---|---|---|---|---|
| D01 | architecture | accepted | passed | passed | passed | hashes_verified | failed | not run |
| D02 | architecture | accepted | passed | passed | passed | hashes_verified | failed | not run |
| D03 | architecture | accepted | passed | passed | passed | hashes_verified | failed | not run |
| D04 | workflow | accepted | failed | failed | failed | not_delivered | not_run_no_delivery | not run |
| D05 | workflow | accepted | failed | failed | failed | not_delivered | not_run_no_delivery | not run |
| D06 | sequence | accepted | passed | passed | passed | hashes_verified | failed | not run |
| D07 | dataflow | accepted | passed | passed | passed | hashes_verified | failed | not run |
| D08 | lifecycle | accepted | passed | passed | passed | hashes_verified | failed | not run |
| D09 | lifecycle | accepted | failed | failed | failed | not_delivered | not_run_no_delivery | not run |
| D10 | workflow | accepted | failed | failed | failed | not_delivered | not_run_no_delivery | not run |
| D11 | dataflow | accepted | passed | passed | passed | hashes_verified | failed | not run |

Schema accepted means the native CLI advanced through schema parsing into render/check; it is not a standalone application-schema validation result. Provenance is deterministic deliver receipt SHA-256 verification, not repository source proof or a v3 strict provenance journal.

## Commands and native receipts

Working directory: `C:\Users\Admin\Downloads\Archify_Upskilling_Run\Archify_Upskilling_Pack\output\archify-upskilling-20260929-181205`. Every `*.execution.json` records actual executable, argv, working directory, exit code and stderr. Commands ran sequentially.

### D01

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" validate architecture D01/candidate.json --quality showcase --json`

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" deliver architecture D01/candidate.json D01/diagram.html --quality showcase --json`

Receipts: [validate](D01/validate-1790677251729.json), [deliver](D01/deliver-1790676995436.json).

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" visual-check D01/diagram.html --json`

Browser receipt: [D01/diagram.visual-check.json](D01/diagram.visual-check.json). Specification SHA-256: `4d133ca0eecca853aad16c7d2361428cb2496a3c57391b672a17fe3707fac77b`; artifact SHA-256: `167ce3c262c70d443eb8270387c65606c811b1283eb7842439f5dc1e1725f14d`.

### D02

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" validate architecture D02/candidate.json --quality showcase --json`

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" deliver architecture D02/candidate.json D02/diagram.html --quality showcase --json`

Receipts: [validate](D02/validate-1790677252241.json), [deliver](D02/deliver-1790676995919.json).

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" visual-check D02/diagram.html --json`

Browser receipt: [D02/diagram.visual-check.json](D02/diagram.visual-check.json). Specification SHA-256: `4c7704b5f636dd099578bbdb86e9b64284099831c79564ea024177d2bab8092d`; artifact SHA-256: `6f48c87ec32abd5ed8c8b58c3d9aac0396995f27241a6279d4f99db27af7d0f5`.

### D03

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" validate architecture D03/candidate.json --quality showcase --json`

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" deliver architecture D03/candidate.json D03/diagram.html --quality showcase --json`

Receipts: [validate](D03/validate-1790677252699.json), [deliver](D03/deliver-1790676996420.json).

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" visual-check D03/diagram.html --json`

Browser receipt: [D03/diagram.visual-check.json](D03/diagram.visual-check.json). Specification SHA-256: `d269a28f8ab1fd317c61371793d092081b032667b818e2959945bee6c912a9f4`; artifact SHA-256: `44454e898a36b7faa6e8fed911b49c6f2af6aa113fbd2c1abb59f7b7bc327b81`.

### D04

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" validate workflow D04/candidate.json --quality showcase --json`

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" deliver workflow D04/candidate.json D04/diagram.html --quality showcase --json`

Receipts: [validate](D04/validate-1790677253155.json), [deliver](D04/deliver-1790677055341.json).

Unresolved native diagnostics:

- [composition/ambiguous-corridor] showcase workflow edges[0] "activate" -> "learn" shares a 124px corridor with edges[8] "validate" -> "review" at [1129, 195] -> [1129, 319] (segments 1 and 2; minimum 8px) — adjust route/via, bias, or channel coordinates so unrelated edges do not visually merge.
- [composition/ambiguous-corridor] showcase workflow edges[1] "correct" -> "generate" shares a 214.8px corridor with edges[8] "validate" -> "review" at [723.6, 195] -> [938.4, 195] (segments 1 and 1; minimum 8px) — adjust route/via, bias, or channel coordinates so unrelated edges do not visually merge.

### D05

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" validate workflow D05/candidate.json --quality showcase --json`

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" deliver workflow D05/candidate.json D05/diagram.html --quality showcase --json`

Receipts: [validate](D05/validate-1790677253496.json), [deliver](D05/deliver-1790677055708.json).

Unresolved native diagnostics:

- [composition/proper-crossing] showcase workflow edges[0] id "approve-to-publish" "approve" -> "publish" crosses edges[10] id "submit-to-grade" "submit" -> "grade" at [739.4, 291] (segments 0 and 3) — adjust route/via, bias, or channel coordinates so the edges use separate lane corridors.
- [composition/proper-crossing] showcase workflow edges[10] id "submit-to-grade" "submit" -> "grade" crosses edges[11] id "validate-to-review" "validate" -> "review" at [443.2, 291] (segments 3 and 1) — adjust route/via, bias, or channel coordinates so the edges use separate lane corridors.
- [composition/ambiguous-corridor] showcase workflow edges[6] id "regrade-to-grade" "regrade" -> "grade" shares a 53px corridor with edges[7] id "review-to-approve" "review" -> "approve" at [658.4, 367] -> [658.4, 420] (segments 1 and 2; minimum 8px) — adjust route/via, bias, or channel coordinates so unrelated edges do not visually merge.

### D06

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" validate sequence D06/candidate.json --quality showcase --json`

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" deliver sequence D06/candidate.json D06/diagram.html --quality showcase --json`

Receipts: [validate](D06/validate-1790677253826.json), [deliver](D06/deliver-1790676996925.json).

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" visual-check D06/diagram.html --json`

Browser receipt: [D06/diagram.visual-check.json](D06/diagram.visual-check.json). Specification SHA-256: `e14aac926b5a1e2720df586990c706f7069e43f05f82a7f409f911264602995f`; artifact SHA-256: `ffdb4ac6ad7eed340b2487dfdb09a167eb468fceb29bdaaeeb765a37cbdbc113`.

### D07

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" validate dataflow D07/candidate.json --quality showcase --json`

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" deliver dataflow D07/candidate.json D07/diagram.html --quality showcase --json`

Receipts: [validate](D07/validate-1790677254267.json), [deliver](D07/deliver-1790676997417.json).

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" visual-check D07/diagram.html --json`

Browser receipt: [D07/diagram.visual-check.json](D07/diagram.visual-check.json). Specification SHA-256: `e7fce762b15f4cee8623ad5ea69a8e721cfe32a4550c496e324d88801f07b073`; artifact SHA-256: `84f9d7ce8dba8e4002c67c3af48c4de01e7951e21e580103aaf883e2c295ae9e`.

### D08

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" validate lifecycle D08/candidate.json --quality showcase --json`

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" deliver lifecycle D08/candidate.json D08/diagram.html --quality showcase --json`

Receipts: [validate](D08/validate-1790677254727.json), [deliver](D08/deliver-1790677054814.json).

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" visual-check D08/diagram.html --json`

Browser receipt: [D08/diagram.visual-check.json](D08/diagram.visual-check.json). Specification SHA-256: `233a9ecc1287fcfef8368466112415bb980ed08a662fb5793ffbd10a8867af9f`; artifact SHA-256: `a59a776e03693356e71584a86d5fcbf5b40e3c456037c4e81f4e746bc0085a1b`.

### D09

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" validate lifecycle D09/candidate.json --quality showcase --json`

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" deliver lifecycle D09/candidate.json D09/diagram.html --quality showcase --json`

Receipts: [validate](D09/validate-1790677255162.json), [deliver](D09/deliver-1790677056063.json).

Unresolved native diagnostics:

- [composition/proper-crossing] showcase lifecycle transitions[5] id "review-pending-to-changes-requested" "review-pending" -> "changes-requested" crosses transitions[6] id "grading-to-technical-failure" "grading" -> "technical-failure" at [556, 365] (segments 2 and 0) — adjust route/via or channelX/channelY so the transitions use separate lifecycle corridors.
- [composition/proper-crossing] showcase lifecycle transitions[5] id "review-pending-to-changes-requested" "review-pending" -> "changes-requested" crosses transitions[7] id "technical-failure-to-grading" "technical-failure" -> "grading" at [632, 365] (segments 2 and 1) — adjust route/via or channelX/channelY so the transitions use separate lifecycle corridors.
- [composition/label-route-clearance] showcase lifecycle label "Retry budget" on transitions[7] id "technical-failure-to-grading" "technical-failure" -> "grading" label "Retry budget" is 0px from transitions[5] id "review-pending-to-changes-requested" "review-pending" -> "changes-requested" label "More evidence" segment 2 [812, 365] -> [402, 365] (label rect [593, 349, 71, 16]; minimum 4px) — adjust labelAt, labelDx, labelDy, or labelSegment; otherwise adjust the other relationship route/via/channel.

### D10

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" validate workflow D10/candidate.json --quality showcase --json`

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" deliver workflow D10/candidate.json D10/diagram.html --quality showcase --json`

Receipts: [validate](D10/validate-1790677255378.json), [deliver](D10/deliver-1790677056290.json).

Unresolved native diagnostics:

- Workflow edge "publish-to-later" has explicit geometry that violates readable route feasibility with authored endpoint sides.

### D11

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" validate dataflow D11/candidate.json --quality showcase --json`

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" deliver dataflow D11/candidate.json D11/diagram.html --quality showcase --json`

Receipts: [validate](D11/validate-1790677255897.json), [deliver](D11/deliver-1790676997903.json).

`node "C:/Users/Admin/.codex/skills/archify/bin/archify.mjs" visual-check D11/diagram.html --json`

Browser receipt: [D11/diagram.visual-check.json](D11/diagram.visual-check.json). Specification SHA-256: `9d365ad0effaf7f14ac3ffe3e82d32ef9f927325754ba93fd42ea6e800345fbc`; artifact SHA-256: `a1bf63101a9c7a12797a04ba30a5839b21f7603094c40495221e55a8bca8c5de`.

## Repair and browser limits

Candidates and failed receipts were preserved across two focused repair rounds and one evidence-based retry. Four views remain blocked; no standard-quality fallback or hand-edited pass was substituted. Interactive source review HTML files are explicitly not native diagrams.

Archify visual-check detected Chrome but failed before any viewport measurements or screenshots: `Chrome DevTools process exit failed: Chrome closed with exit code 0`. The supported ARCHIFY_CHROME override to local Chromium reproduced the same error. Browser evidence is failed, not skipped. No screenshots/contact sheets were produced.

Supplementary computer-use inspection: in-app browser unavailable. The Chrome browser tool rejected local file navigation under its URL policy and explicitly prohibited workarounds. No alternate serving/transport bypass was attempted. Perceptual review is not run because no rendered surface could be reached. The four required desktop viewport and light/dark reviews remain outstanding.

The installed Archify SKILL.md requires stopping after non-improving repairs; its delivery contract caps focused perceptual correction at two rounds. See toolchain-evidence/SKILL.md and references/delivery-contract.md. No approval question is needed to deliver the independent local files.
