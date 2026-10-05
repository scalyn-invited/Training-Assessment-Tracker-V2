# Realistic mock data for manual testing

Prepared: 5 October 2026. All people, organisations, work records, scores and observations below are fictional. This is a manual test pack, not an executed test report or an import file.

Scenario: **Harbour Desk Services**, a fictional operations team, needs a junior assistant to produce procedures that another person can follow without guessing. The learner progresses from documenting a routine task to handling an exception and handing over a complete procedure.

## 1. Accounts and a fresh test enrolment

Use the local app at `http://127.0.0.1:8000` after starting it using [README](../README.md). Keep the sandbox identity, mock AI and non-delivering email settings. The worker must consume `ai_generation`, `ai_grading` and `notifications` for those exercises; see [local workflows](LOCAL_WORKFLOWS.md).

The default seed provides Alex (administrator), Casey (Operations coordinator), Morgan (Support coordinator), Sam (Operations learner) and Jordan (Support learner). Seeding does not automatically create the additional learner below.

For a repeatable run, sign in as **Alex**, open **Administration**, and create these synthetic records using the available forms:

| Field | Value |
| --- | --- |
| Learner name | Nina Reyes - Synthetic QA |
| Email | nina.reyes.qa01@example.invalid |
| Role | member |
| Synthetic | Yes |
| Group | Operations |
| Accountable coordinator | Casey |
| Enrolment title | QA-OPS-001: Clear and reproducible operations procedures |
| Reason, where requested | Create a fictional learner and isolated enrolment for manual acceptance testing. |

Assign Nina to Operations before creating the enrolment. Casey must have an effective coordinator assignment to that group. Sign out after administration changes and choose Nina's new mock identity when testing as the member. For later runs, use a new suffix such as `qa02` and a distinct enrolment title. Do not overwrite an active training history or reset the database.

You can instead use Sam's existing **Clear, reproducible procedures** enrolment if it is still in onboarding and has no conflicting capacity reservations. Substitute Sam for Nina throughout. A fresh learner avoids conflicts with earlier experiments on Sam's shared calendar.

## 2. Copy-ready onboarding values

Sign in as **Casey**, open Nina's enrolment, then **Onboarding and plan setup**. Wait for **Draft saved** before moving between steps.

### Profile and role

| UI field | Copy this value |
| --- | --- |
| Current role | Junior operations assistant at fictional Harbour Desk Services. |
| Responsibilities | Maintain the daily request register, check incoming handovers, record exceptions and prepare procedures for peer review. |
| Experience | Six months using spreadsheets and shared documents. Can complete familiar tasks but often omits inputs, exception handling and verification steps when documenting them. |
| Tools available | Plain-text editor, spreadsheet application and the synthetic records supplied in this test pack. No customer systems or live accounts. |
| Language and accessibility preferences | Plain English, numbered instructions, descriptive headings and keyboard-accessible controls. Avoid colour-only instructions. |

### Desired capability

| UI field | Copy this value |
| --- | --- |
| Target role or skill | Independently write, test and maintain reproducible operations procedures. |
| Business purpose | Reduce missing handover details and repeated clarification requests in a fictional operations team. |
| Expected proficiency | Another team member can follow the written steps, identify exceptions and verify the result without verbal help. |
| Practical examples of success | A peer can process a synthetic request, detect a duplicate, escalate an incomplete record and record the outcome using the learner's procedure. |
| Prerequisite skills | Read a simple request table, edit a document, compare record identifiers and calculate a basic count. |

**Target competencies (one per line):**

```text
Document inputs, ordered actions and verifiable outputs for an operations task.
Identify incomplete or duplicate records and document a safe escalation path.
Test a procedure with a peer and revise it using recorded evidence.
```

### Assessment findings

| UI field | Copy this value |
| --- | --- |
| Source or assessment reference | QA-BASELINE-001; fictional desk exercise recorded in this Markdown test pack. |
| Provider or observer | Casey, acting as the synthetic Operations reviewer. |
| Assessment date | 2026-10-05 |
| Scoring scale (or not scored) | Fictional baseline: 58 out of 100 on a procedure-writing exercise; planning context only, not an official approved grade. |
| Evidence type | observed evidence |

**Findings to confirm:**

```text
In a fictional ten-record exercise, Nina identified eight complete records but failed to identify one duplicated request ID. Her instructions named the task but omitted the required input columns, the action for a missing owner, and a final count check. A peer needed three clarifications to finish. Nina correctly kept the original records and used clear numbered steps. Training should focus on explicit inputs, exception handling and evidence of independent peer execution. These observations are invented for testing; no real employee was assessed.
```

Click **Confirm assessment findings** after saving the final text. Any later edit requires confirmation again. Manual entry is intentional: this test does not require OCR or an uploaded file. The assessment date must not be in the future on the machine running the app.

### Capacity and calendar

| UI field | Value |
| --- | --- |
| Learning blocks | 4 |
| Minutes per day for this plan | 30 |
| Shared daily capacity (all plans) | 45 |
| Start date | 2026-10-12 |
| Calendar timezone | Asia/Manila |
| Scheduled weekdays | Monday only; uncheck Tuesday through Sunday |
| Business holidays | Leave empty for this controlled fixture |
| Approved absence dates | Leave empty for the baseline run |
| Workload constraints and rescheduling notes | One 30-minute Monday session per week, including reading, practice, assessment and revision. Preserve 15 minutes of spare shared capacity. Reschedule explicitly if leave is introduced. |

This intentionally small programme has **four lessons, one per block**, totalling **120 minutes**. Empty holidays are a test assumption, not a claim about the actual Philippine holiday calendar.

| Block | Lesson date | Expected adaptation cutoff, Asia/Manila |
| --- | --- | --- |
| 1 | 2026-10-12 | 2026-10-08, 17:00 |
| 2 | 2026-10-19 | 2026-10-15, 17:00 |
| 3 | 2026-10-26 | 2026-10-22, 17:00 |
| 4 | 2026-11-02 | 2026-10-29, 17:00 |

If testing after these dates, move the start to a future Monday at least two weeks away and regenerate the schedule before creating the baseline. Use the app's displayed cutoffs. Keep the assessment date on or before today. Do not change the computer clock to test this pack.

### Measures of success

Enter these three measures in order. Use the unit strings exactly: the current implementation seeds `percent` as an approved-grade mean, and other units as manually observed means. All start with a minimum of **three** samples and a higher-is-better direction.

| Field | KPI 1 | KPI 2 | KPI 3 |
| --- | --- | --- | --- |
| Name | Procedure quality | Correctly handled requests | Independent handover checks |
| Baseline | 58 | 7 | 3 |
| Target | 85 | 9 | 4 |
| Unit | percent | requests per batch of 10 | checks per handover out of 5 |
| Why this matters | Measures demonstrated quality of written procedures. | Checks whether procedure use results in correct handling of comparable request batches. | Checks whether handovers can be verified without verbal clarification. |
| Evidence method | Human-approved rubric results from this programme's four practical lessons. | Coordinator records correct outcomes for each independent synthetic ten-request batch. | Coordinator records independently completed checks for each synthetic handover. |
| Review cadence | After each approved lesson. | Three separate simulated batches after practice. | Three separate simulated handovers after practice. |

The two workplace measures simulate observation entry only; they do not prove real workplace improvement. Baseline values are context, not extra observations.

### Learning resources

| UI field | Copy this value |
| --- | --- |
| Approved resource title | Harbour Desk synthetic procedure handbook v1 |
| Resource URL or internal reference | platform/docs/REAL_WORLD_MOCK_TEST_DATA.md, sections 3 and 4 |
| Licence or permission to use | Original fictional test material supplied for internal sandbox testing. |
| Cost and budget approval | No external resource purchase or paid AI call is required. |
| Member access and required software checked | Tester has this Markdown file and a text editor; all exercise records are included below. |
| Suitable practical tasks | Write a register-checking procedure, add exception handling, record a peer test and produce a final handover. |

In **Review and create**, confirm the shared calendar with reason `Confirm the fictional four-session schedule and shared 45-minute capacity.` Then create the curriculum draft with reason `Create a manual baseline for QA-OPS-001 using the supplied fictional handbook.`

## 3. Synthetic handbook and source records

Rules for this exercise:

1. Preserve the source register; work on a copy.
2. A request is complete when request ID, owner, due date and status are present.
3. Compare request IDs before processing. Hold a repeated ID for coordinator review; do not delete or merge it automatically.
4. Hold a request with a missing owner. Ask the coordinator to identify the owner; do not invent one.
5. Record each row's outcome and reason. Check that every input row appears in the outcome log.

| Row | Request ID | Owner | Due date | Status |
| --- | --- | --- | --- | --- |
| 1 | QA-REQ-101 | Avery | 2026-10-13 | New |
| 2 | QA-REQ-102 | Blake | 2026-10-13 | New |
| 3 | QA-REQ-103 | Casey | 2026-10-14 | New |
| 4 | QA-REQ-104 | Drew | 2026-10-14 | New |
| 5 | QA-REQ-105 | Ellis | 2026-10-15 | New |
| 6 | QA-REQ-106 | Frankie | 2026-10-15 | New |
| 7 | QA-REQ-107 | Gale | 2026-10-16 | New |
| 8 | QA-REQ-108 | Harper | 2026-10-16 | New |
| 9 | QA-REQ-108 | Harper | 2026-10-16 | New |
| 10 | QA-REQ-110 | [missing] | 2026-10-16 | New |

Expected outcome: eight first-occurrence complete rows are ready, row 9 is held as a duplicate and row 10 is held for a missing owner. The outcome log accounts for **10 rows** and **9 distinct request IDs**. Do not silently discard the duplicate row from the count.

## 4. Four complete manual lessons

Use the curriculum editor. Each block contains one lesson under the Monday-only schedule. Enter the block and lesson values below, then **Save new draft version** for each block. All four lessons link to **KPI number 1** so that its grade mean can reach the three-sample minimum.

Common values for every lesson:

- Permitted tools: `Text editor, spreadsheet and section 3 of the synthetic handbook. No live systems.`
- Reading: **5** minutes; practice: **15**; assessment: **5**; revision: **5**. Total **30**.
- Criterion one: `Accuracy: identify the correct inputs, preserve source records and apply the handbook rules correctly. Cite the relevant rows or procedure steps.` Weight **0.60**.
- Criterion two: `Reproducibility: provide ordered actions, explicit exception handling and a verifiable outcome so a peer can execute without guessing.` Weight **0.40**.
- Reason for this version: `Add the reviewed fictional lesson and evidence requirements for this block.`

### Block 1: Make a task reproducible

| Field | Copy this value |
| --- | --- |
| Learning objective | Write a procedure with explicit inputs, ordered actions and a checkable output. |
| Prerequisites and sequence | Basic document editing and reading the synthetic request table; first block in the sequence. |
| Target competency number | 1 |
| Lesson title | Check a request register without guessing |
| Explanation and learning content | A usable procedure identifies its input, required columns, action order and completion check. Preserve the original register. Inspect rows in order and record an outcome for every row. An instruction such as check the data is incomplete unless it says which fields to inspect and how to handle a failure. |
| Worked example or approved demonstration | For row 1, confirm QA-REQ-101 has an owner, due date and status, check that its ID has not already appeared, then record ready. Preserve the original row and cite row 1 in the outcome log. |
| Practical activity and deliverable | Write six numbered steps for checking the ten-row register. Include the required columns, a preserved source copy, an outcome log and the final count check. |
| Completion criteria and evidence required | Submit six steps with explicit inputs, no source deletion, an outcome for each row and a reconciliation of all ten rows. |

### Block 2: Handle exceptions

| Field | Copy this value |
| --- | --- |
| Learning objective | Explain how to hold duplicate and incomplete requests without inventing data. |
| Prerequisites and sequence | Build on the input, action and output structure from block 1. |
| Target competency number | 2 |
| Lesson title | Write a duplicate and missing-owner decision path |
| Explanation and learning content | An exception is a documented branch, not a reason to guess. A repeated ID is held for review and a missing owner is escalated. Keep the original row, its reason for being held and the person asked to resolve it. Resume only after an explicit decision. |
| Worked example or approved demonstration | Row 9 repeats QA-REQ-108 from row 8, so hold row 9 and ask the coordinator to resolve the duplication. Row 10 has no owner, so hold it and request an owner. Do not delete row 9 or assign yourself to row 10. |
| Practical activity and deliverable | Write the normal, duplicate and incomplete-record branches. Apply them to rows 8, 9 and 10 and produce a three-row decision log. |
| Completion criteria and evidence required | Submit the three branches and a log showing row 8 ready, row 9 held for duplication and row 10 held for missing owner, with escalation and restart conditions. |

### Block 3: Test with a peer

| Field | Copy this value |
| --- | --- |
| Learning objective | Use recorded peer-test evidence to remove ambiguity from a procedure. |
| Prerequisites and sequence | Use the normal and exception paths developed in blocks 1 and 2. |
| Target competency number | 3 |
| Lesson title | Run and record a dry-run handover |
| Explanation and learning content | A peer test checks the written instructions rather than the author's memory. Record where the peer stops, the exact missing instruction and the revision made. Re-run the affected step and record the outcome. Mark role-play observations as simulated rather than claiming a real peer test. |
| Worked example or approved demonstration | The instruction verify totals caused a question about whether duplicates count. Revise it to account for all ten source rows, including held rows, then verify eight ready plus two held equals ten. |
| Practical activity and deliverable | Role-play an independent reader using the procedure. Record two clarification points, the changes made and a second-run result. Label the log as simulated. |
| Completion criteria and evidence required | Submit a before/after change log with two specific revisions and a simulated re-test showing ten accounted-for rows and no unresolved counting question. |

### Block 4: Produce the final handover

| Field | Copy this value |
| --- | --- |
| Learning objective | Deliver a versioned procedure with ownership, exception handling and verified results. |
| Prerequisites and sequence | Incorporate the recorded revisions from block 3 and the checks from blocks 1 and 2. |
| Target competency number | 3 |
| Lesson title | Publish a complete synthetic procedure package |
| Explanation and learning content | A handover package identifies its version, owner, input, ordered steps, exception contact, outcome and unresolved items. The recipient must be able to distinguish a ready record from a held record. Approval of this exercise does not authorise changes to a real operations system. |
| Worked example or approved demonstration | Version 1.1, owner Nina, uses register QA-OPS-001 and records eight ready rows plus two held rows. Casey receives the duplicate and missing-owner queries. The revision note explains the improved count check. |
| Practical activity and deliverable | Combine the procedure, exception log and simulated peer-test revisions into a short final handover. Include version, owner, result counts, unresolved items and next actions. |
| Completion criteria and evidence required | Submit the full package with ten reconciled rows, two clearly owned exceptions and a revision history that explains the changes after testing. |

After saving all blocks, click **Validate all blocks and request review**, then approve with rationale `Reviewed all four lessons, source records, rubric weights and time budgets for this synthetic exercise.` Open **Today and learning plan** and activate with reason `Activate the reviewed fictional four-session baseline for manual testing.`

### Optional AI-generation smoke test

Run this on a separate draft/enrolment before entering the manual lessons if you want to preserve the realistic curriculum. Open **Generate or revise with AI** and enter:

```text
Use the confirmed procedure-writing competencies and the supplied synthetic register. Include duplicate detection, missing-owner escalation and a peer-test log. Keep every session within 30 minutes including revision. Preserve the approved KPI targets and calendar.
```

The current local mock returns deterministic demonstration lessons; it **does not tailor lessons to these notes**. Expected result: a generated **draft** with provenance and human review still required. Do not treat generic mock lessons as a failure of this data pack or as real provider validation.

## 5. Learner submissions and review data

Before a future lesson can accept work, Casey must explicitly authorise early start with reason `Permit an accelerated synthetic test of this block without changing the planned calendar.` Publish any optional objective quiz before early start or the freeze cutoff. For the main practical workflow, leave quizzes unset.

As Nina, open the available lesson, paste the relevant response into **Your response**, wait for **Draft saved**, reload once to check persistence, and then submit. Keep the receipt URL.

### Block 1: incomplete first attempt

```text
I opened the register, checked the data and removed the repeated row. I sent the list to the next person. Everything looked correct.
```

Expected human decision: **Request changes**, not an automatic pass. Suggested review rationale:

```text
The response deletes a source row, does not define completeness, omits the missing-owner escalation and provides no reconciliation. Preserve all ten rows and supply explicit steps with an outcome log before resubmitting.
```

### Block 1: revised response

```text
QA-OPS-001, revised attempt, synthetic data only.
1. Preserve the original ten-row register and make a working copy.
2. Confirm the columns request ID, owner, due date and status exist.
3. Inspect rows in order and record each first-seen complete ID as ready.
4. Hold repeated IDs for coordinator review. Row 9 repeats QA-REQ-108 from row 8; retain both rows and log the duplicate.
5. Hold any row missing a required field. Row 10 has no owner; ask Casey to confirm the owner before proceeding.
6. Reconcile the outcome log: rows 1-8 ready, row 9 duplicate hold, row 10 missing-owner hold. Eight ready plus two held equals ten source rows. There are nine distinct request IDs. Preserve the source and send the two exception queries to Casey.
```

### Block 2 response

```text
Synthetic decision log: row 8 / QA-REQ-108 / ready, because required fields exist and this is its first occurrence. Row 9 / QA-REQ-108 / held, because its ID repeats row 8; Casey must confirm the intended record before release. Row 10 / QA-REQ-110 / held, because owner is missing; Casey must supply a verified owner before release. Keep every original row. Record the coordinator's decision and repeat the completeness and duplicate checks before changing a held outcome to ready.
```

### Block 3 response

```text
Simulated peer-test log, not a real colleague observation.
Question 1: Does the final count include duplicates? Changed "verify totals" to "account for all ten source rows, including held rows".
Question 2: Can I fill in the missing owner? Added "hold the request, ask Casey, record the decision and recheck before release".
On a simulated second run, the reader logged eight ready rows and two held rows, retained all ten source rows and identified Casey as the owner of both follow-up decisions. No additional clarification was needed for these two revised steps.
```

### Block 4 response

```text
Synthetic procedure package QA-OPS-001, version 1.1, owner Nina Reyes.
Input: the original ten-row request register and a separate working copy.
Process: verify columns; inspect required fields; track IDs in row order; log each row as ready or held; escalate duplicate IDs and missing fields; reconcile all source rows.
Result: eight ready, one duplicate hold and one missing-owner hold; ten rows accounted for and nine distinct request IDs.
Outstanding items: Casey to review row 9 against row 8 and confirm an owner for row 10. Neither held row is released without a recorded decision and recheck.
Revision history: version 1.0 omitted whether held rows count and who supplies missing owners. Version 1.1 clarifies both, following the simulated peer test. This package changes no real operational records.
```

As Casey, request provisional grading and let the worker finish. The mock normally supplies a fixed demonstration score of 70 for readable text; it is not a quality judgment. Nina must not see a provisional numeric grade, and it must not count toward official KPIs.

For the successful completion path, use **Human assessment or override** only after reviewing the relevant response. The following are illustrative test inputs, not marks that real work should receive automatically:

| Approved submission | Criterion one | Criterion two | Weighted total |
| --- | --- | --- | --- |
| Block 1 revised | 90 | 90 | 90 |
| Block 2 | 90 | 85 | 88 |
| Block 3 | 85 | 90 | 87 |
| Block 4 | 95 | 90 | 93 |

Review rationale template: `Synthetic manual review of the submitted text: correct row references, preserved source records, explicit escalation and verifiable outcomes support these criterion scores.` Add the actual submission reference and specific strengths/weaknesses where the form requests evidence. Save and approve the exact current result using the receipt's available controls.

## 6. KPI evidence and completion expectations

In **Progress, KPIs and future support**, refresh the snapshots where necessary.

- KPI 1: one or two approved results must remain **insufficient evidence**, not zero. After the first three successful decisions above: `(90 + 88 + 87) / 3 = 88.33`, on target. After all four: **89.5**, on target. The requested-changes attempt and provisional mock grades must not become additional approved samples.
- KPI 2: use **Record workplace evidence** for each of the three rows below. Expected mean **9.33**, on target after three samples.
- KPI 3: record its three rows separately. Expected mean **4.67**, on target after three samples.

| KPI | Observed value | Evidence reference |
| --- | --- | --- |
| 2 | 9 | QA-BATCH-A: fictional batch of ten requests; nine handled correctly, one exception reason omitted. |
| 2 | 10 | QA-BATCH-B: separate fictional ten-request batch; all outcomes and exception reasons correct. |
| 2 | 9 | QA-BATCH-C: separate fictional ten-request batch; nine correct, one missing escalation contact. |
| 3 | 4 | QA-HANDOVER-A: fictional handover; four of five verification checks completed independently. |
| 3 | 5 | QA-HANDOVER-B: separate fictional handover; all five verification checks completed independently. |
| 3 | 5 | QA-HANDOVER-C: separate fictional handover; all five verification checks completed independently. |

Use reason `Record a distinct fictional observation to test sample counts and evidence lineage.` The five handover checks are version, owner, input reference, reconciled outcomes and exception next actions. These additional batch/handovers are stipulated synthetic observations, not repeated measurements of the single source register. Enter each once; do not duplicate a sample just to reach the minimum.

Before all required work and all three measures are sufficient/on target, completion should be rejected. After all four required lessons have approved results and all three KPIs are on target, Casey can confirm completion with reason `All four synthetic lessons have approved evidence and three sufficient on-target measures; confirm capability for this fictional acceptance-test scenario.`

## 7. Separate branch and negative checks

Perform these before completion, or use a fresh enrolment so they do not disrupt the successful path. Record what actually happens; the table is expected behaviour, not a claim that this pack was executed.

| Test | Input/action | Expected result |
| --- | --- | --- |
| Future preview | Nina previews block 3 before its date, without early start. | Reading preview does not start the block or permit work submission. |
| N+2 adaptation | After approving block 1, before block 3's displayed cutoff and before any block 3 activity, propose adaptation with evidence block `1`; reason `Provide another worked example of preserving duplicate rows and reconciling held requests.` | First candidate is block 3, not block 2; proposal remains provisional until Casey approves. Calendar, capacity and targets stay fixed. |
| Started-block protection | In a separate branch, early-start block 3 before requesting the same adaptation. | Block 3 cannot be replaced; the app considers a later eligible block or final support. |
| Resubmission | Request changes on the incomplete block 1 response, then submit the revised response. | A new receipt/attempt is created; the earlier attempt remains available in history. |
| Stale draft | Open the same response in two tabs. Save newer text in tab A, then attempt a different save from stale tab B. | Conflict prevents silent overwrite; preserve/reconcile the unsaved text. |
| Appeal | Nina appeals an approved decision: `Please review whether the submitted row-count reconciliation was considered under criterion two.` | Original decision stays official pending alternate review. |
| Alternate reviewer | Alex gives Morgan an effective Operations coordinator assignment and assigns the appeal with reason `Assign an independent reviewer for this synthetic appeal.` | Morgan can review the assigned appeal; assignment does not grant unrestricted access to Nina's other records. |
| Peer isolation | Sign in as Jordan and open Nina's copied receipt URL. | Access denied/inaccessible record; no submission body or grade disclosed. |
| Capacity conflict | On a separate test enrolment for the same learner, attempt another 30-minute plan on the same Mondays while the first reserves 30 of the shared 45 minutes. | Approval rejects the combined 60-minute allocation; no silent increase to capacity. |
| Pause/resume | Pause with reason `Fictional one-week absence requires an explicit future schedule review.` Preview a one-week shift before approving resume. | Preview shows future changes; started history is preserved; capacity is checked again at approval. |
| Upload quarantine | Upload a harmless text fixture containing `Synthetic evidence only. No employee data.` | Without a qualified scanner it remains quarantined; it must not be treated as clean evidence. |
| Notifications | Approve a result, then inspect Nina's Updates. | In-app notice remains available; synthetic email uses a sink and sends no real message. |

For objective-quiz testing, create a separate draft programme with quiz-specific competencies and criteria. This pack's practical writing rubric should not be replaced by easy multiple-choice questions merely to obtain a passing grade.

## 8. Manual test record

Copy this table for each run. Replace placeholders with actual values; do not pre-mark tests passed.

| Item | Actual result |
| --- | --- |
| Tester and local date/time | Pending |
| Branch/commit | Pending |
| Learner and enrolment reference | Pending |
| Actual start date and timezone | Pending |
| Confirmed assessment/calendar revisions | Pending |
| Approved baseline version | Pending |
| Submission and approved decision references | Pending |
| Three KPI snapshot references | Pending |
| Completion result | Pending |
| Negative/branch checks attempted | Pending |
| Issues, steps to reproduce and screenshots | Pending |

This pack exercises local workflows. It does not validate live SSO, real AI accuracy, SMTP delivery, OCR, actual staff outcomes or production readiness. See [remaining milestones](REMAINING_MILESTONES.md) for those boundaries.
