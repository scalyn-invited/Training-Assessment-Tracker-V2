# Milestone 02 — onboarding and curriculum setup

This increment implements the local B03 learning setup journey. AI generation, live identity/directory integration, extraction/scanning and active learning delivery remain separate work.

## Member and coordinator workflow

1. Open an enrolment from Workspace, then **Onboarding and plan setup**.
2. Members can supply their profile, assessment summary and availability. The assigned coordinator owns targets, competencies, measures and approved resources.
3. Editable onboarding steps autosave to server-side revision history. Wait for “Draft saved” before leaving. Validation, offline and stale-version errors retain the current form input. A stale editor must copy its changes and reload before resubmitting; there is no silent overwrite or sensitive browser-storage copy.
4. Assessment input is manually attributed as a provider score, self report or observed evidence. The coordinator explicitly confirms findings. Editing assessment fields removes that confirmation. Existing upload quarantine remains in force; this increment does not extract or approve uploaded files.
5. At **Review and create**, the coordinator confirms the shared member calendar with a reason, then creates a curriculum draft. Both operations check the exact onboarding revision.
6. Edit every block's objectives, prerequisites, competency, rubric and dated lessons. A lesson includes explanation, worked example, practical activity, tools, completion evidence and separate reading/practice/assessment/revision minutes. Reference the reviewed onboarding resource and selected KPI.
7. **Save new draft version** preserves prior content. Version history and block comparison expose changes. An edit invalidates pending review. An approved baseline remains current until a replacement is approved.
8. **Validate all blocks and request review** checks completeness, 15–120 minute daily totals, plan budget, exact calendar dates, rubric weights and shared capacity. **Approve exact version** requires the accountable coordinator, a reason and a fresh recheck. Approval records actor, content hash and exact version; the enrolment becomes ready, not active.

## Calendar and concurrency rules

- Allowed durations: 4, 6, 8, 10 or 12 dated learning blocks of seven calendar days each.
- The calendar explicitly records IANA timezone, selected weekdays, holidays, absences and total daily capacity across programmes.
- Each scheduled day must contain 15–120 minutes and fit the agreed plan budget. All four workload components count.
- Calendar revisions and approvals serialize on the member row. This protects concurrent enrolments, not just one plan at a time.
- Approved capacity reservations are replaced atomically; old allocations and content remain as history.
- An absence/holiday/capacity change that conflicts with an approved reservation is rejected. To reschedule a ready enrolment, first propose a later start using its existing confirmed calendar, create a refreshed draft, review copied lessons and approve the replacement. Then confirm the additional absence dates. No dates shift silently.
- A timezone change with reservations is blocked; coordinated timezone migration and pause/resume of active work remain later lifecycle work.
- The freeze timestamp is 17:00 local time on the second preceding business day, excluding business holidays, stored as UTC in the plan snapshot. Learner absence does not redefine business-day cutoffs.
- Changes to either onboarding or the shared calendar invalidate pending plan edits/approval until a refreshed draft is created.
- New learning mutations reject active/completed/cancelled enrolments; completed work cannot be rewritten by these setup routes.

## Verification

Executable coverage lives in:
- `tests/Feature/LearningTest.php`: persistence, stale-save conflicts, role/scope/confirmation boundaries, all duration and minute limits, holiday/timezone cutoffs, lesson/rubric validation, exact approvals, immutable history, overlapping plans, calendar conflicts and revocation/self-approval rejection.
- `tests/Integration/CapacityConcurrencyTest.php`: two real PHP processes attempt competing approvals against MariaDB; exactly one succeeds and one is rejected for shared capacity.
- `tests/Browser/learning.spec.js`: mobile autosave/reload/network/conflict recovery and the complete coordinator onboarding/editor/approval journey, with accessibility checks.

Run the existing SQLite, MariaDB and browser commands in the application README. Browser setup resets only the fixed ignored `.runtime/browser.sqlite` fixture; normal application setup remains non-destructive.

## Scope boundaries

- Manual confirmation and manual content editing are implemented. Document extraction, AI interpretation, asynchronous generation/revision and provider gateways remain B04/integration work.
- The current resource input captures one reviewed source with permission, cost, access and practical-task context. It does not fetch URLs or operate a general resource catalogue.
- The wizard captures three planning KPIs; calculation formulas, effective-dated official targets and observations remain B06.
- Existing scoped enrolments provide the entry point. New member/group/enrolment management remains pending.
- Approval establishes a complete baseline and reserves capacity. Training activation, Today/lesson reader, submissions, grading, appeals and SMTP remain B05.
- T06 and T12 remain partial: manual versioned approval and calendar rules are covered; AI revision, active pause/resume and adaptation publication are not implemented here. Automated accessibility tests are not a full manual screen-reader audit.
