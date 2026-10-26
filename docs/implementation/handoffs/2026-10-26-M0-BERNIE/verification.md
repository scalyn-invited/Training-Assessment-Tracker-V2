# M0 technical verification and self-review

Date: 2026-10-26, Asia/Manila. Author: Bernie's AI implementation session. Source baseline `8ce63129b5985c6edd86361df863ed960c999bfc`; branch `codex/m0-bernie-reconciliation`; documentation commit `6f1dafa`, pushed to origin; no PR/hosted CI is claimed yet.

## Environment and reproduction

Runtime: `.runtime/m0-bernie-20261026/platform`, a separate physical copy of application/dependencies, not a linked shared storage/database. No source `.env`, source storage, source `.runtime` or `.git` was copied. Existing PHP/Node dependencies were reused by copying, with no download, lockfile change or new licence. PHP CLI: 8.2.12. Source Composer permits PHP ^8.2 / Laravel ^12.0; this is not new production version qualification.

The broad initial platform copy included an existing ignored SQLite file. Initial setup against that isolated copy reported nothing to migrate; this is not claimed as a fresh setup. A new empty `database/m0-setup.sqlite` was then created inside the task runtime, explicitly selected in its local `.env`, migrated and seeded. The new database contains one organisation, five users (all synthetic) and two enrolments. Runtime storage/cache/session/view paths are inside the copy; no persistent worker is shared or started. [Isolation evidence](evidence/isolation.json) records nonsecret settings and counts. All live AI disabled, array mailer, training environment test, mock identity enabled.

The environment-path attempt initially failed because spaces were unquoted; a quoting retry retained a newline. Final relative `DB_DATABASE=database/m0-setup.sqlite` corrected both. These failed attempts and final setup are retained. The source database is not a reset target. Browser preparation resets only the separate copy's `.runtime/browser.sqlite` after its existing path guard. PHPUnit configuration forces SQLite `:memory:`.

## Actual commands and outcomes

Commands below run from the isolated platform directory unless noted. Logs are retained under [evidence](evidence/README.md); ignored runtime reports are supplementary, not required to recover the recorded result.

| Command / scenario | Result | Limits |
| --- | --- | --- |
| `git switch -c codex/m0-bernie-reconciliation` from repository root | Passed after authorised elevation for Git metadata write | Branch created locally; no remote sync/push. Source HEAD unchanged. |
| pypdf extraction of every page, source PDF page count | Passed: 26 pages, sections 1-37 read | Initial console encoding failure corrected with PYTHONIOENCODING=utf-8. Page-9 Poppler render visually checked; no source PDF edits. |
| `php scripts/setup.php` with fresh runtime database | Passed: migrations and synthetic seed | Earlier copied-database run and invalid dotenv attempts retained; successful evidence is setup-clean.txt. No production/shared DB target. |
| `php artisan test --compact` / direct PHPUnit attempts under restricted runner | Failed before tests: bootstrap not readable | PHP could load the same file but `is_readable(realpath(...))` returned false. Failure was runner-dependent, not a passing test result. |
| `php artisan test --compact --log-junit ../php-results.xml` with authorised elevated runner | **107 passed, 4 skipped; 587 assertions; 77.53 seconds** | Entire existing SQLite feature suite; no added/changed tests. JUnit retained. Does not run MariaDB concurrency suite. |
| `npm.cmd run test:browser` initial restricted runner | Stalled after synthetic seed; stopped with task session interrupt | No browser completion claimed for that attempt; task-specific process inspection found no remaining prepare/migrate/playwright process before retry. |
| `npm.cmd run test:browser` authorised elevated retry | **7 passed in 2.7 minutes**, no failed tests | Existing seven scenarios; fresh port-8123 check before launch; reuseExistingServer=false. |
| `php vendor/bin/pint --test` | **Passed** | Read-only formatting check, no formatter rewrite. |
| MariaDB/concurrency suite | **Blocked / not run** | No verified dedicated synthetic MariaDB database; no listener found on configured port 3308. Do not run against potentially shared `training_foundation_test` merely because its name is in XML. |
| Restore/load, live identity/API/provider/SMTP and representative human pilot | **Not run** | No need to rerun operational rehearsals for M0 docs; live/data/cost/release authorisation and services absent. |

### Four explicit skips

- `DocumentParserTest::test_docx_text_and_tables_are_read_without_fetching_resources`: PHP ZipArchive unavailable.
- `DocumentParserTest::test_docx_rejects_external_relationships_and_entities`: same ZipArchive prerequisite.
- `DocumentParserTest::test_real_pdf_and_image_ocr_tools`: complete application Poppler/Tesseract configuration absent. Using bundled Poppler for source-PDF inspection does not qualify application OCR.
- `DocumentScannerTest::test_real_clamav_gates_extraction_using_a_harmless_custom_signature`: expected `/usr/bin/clamscan` unavailable. No clean scanner/production signature claim follows.

## Permission evidence in the passing SQLite suite

These are actual existing FoundationTest scenarios included in the fresh run. JUnit names identify each precisely.

| Scenario | Observed assertion coverage | Remaining qualification |
| --- | --- | --- |
| `test_member_sees_own_records_without_peer_counts_or_files` | Own enrolment count, peer plan/file 404, people 403 | Repeat against explicit multi-client grants and later report/chat surfaces. |
| `test_cross_organisation_and_environment_records_are_hidden` | Mismatched organisation/environment enrolment 404 | Mutation-based fixture, not full two-client/two-teams-each scenario. |
| `test_coordinator_requires_effective_assignment_and_named_responsibility`, `test_future_assignment_cannot_grant_access` | Unassigned/ended/future assignments denied | Future team/cohort/grant model not implemented. |
| `test_admin_needs_separate_sensitive_file_capability` | Admin denied sensitive file until capability flag explicitly set | Current admin otherwise has organisation-wide enrolment visibility; new personal-report capability model remains D02. |
| `test_disabled_identity_and_account_revoke_session`, `test_directory_deactivation_invalidates_existing_delegation_and_session` | Existing session/delegation revoked | Actual external IdP notification/freshness unverified. |
| `test_stale_permissions_fail_closed_for_privileged_and_external_reads` | 503 for stale imported privileged/external access | Actual parent permission contract awaits discovery. |
| `test_queued_job_rechecks_revocation_and_version_before_delivery`, `test_job_status_and_livewire_receipts_are_scoped_to_actor` | Cancel revoked/version-changed work; other actor cannot read receipt/job | Extend equivalent checks to future exports, chat and new notifications. |
| `test_membership_removal_revokes_member_and_coordinator_record_access` | Effective membership removal denies both actors | No claim of full future cross-client transfer/history handling. |

DeliveryTest additionally checks provisional grades hidden until exact human approval, forged evidence rejected and revocation before grading/notices. LearningTest checks self-approval denial, shared capacity, version/calendar staleness and peer history denial. ProgressionTest checks approved-only KPI evidence, insufficient evidence, N+2/freeze/started constraints. PilotWorkflowTest reproduces an accelerated synthetic onboarding-to-completion journey with 12 approved lesson submissions and no HTTP calls. It is not initial-assessment MVP acceptance or a real human pilot.

## Technical self-review — producer review, not independent acceptance

Reviewed against source HEAD above and the manifest-identified dirty documentation delivery. Review checks: source PDF coverage/requirement IDs, actual code versus historical claims, unresolved decisions explicitly labelled, no fabricated Cerebru API, proposal contracts unfrozen, local tickets bounded, live/cost gates retained, no application changes, shared-register/counterpart ownership preserved. Final hash/link/baseline checks are recorded in the completion addendum and machine-readable validation evidence.

Findings carried forward: historical UI/branding handoff does not match this HEAD; one-organisation seed is insufficient for M1; fixed roles/admin breadth and group typing require D01/D02; explicit 30-day mode, weekly reports, private notes, growth/report exports, initial assessment assignment and chat remain missing; live/utility/MariaDB qualification is incomplete. Shared handoff README still says none exist, despite dated handoffs; this stale shared-file wording is flagged, not silently edited.

No blocker requires inventing a product rule to complete this documentation lane. Product decisions and shared coordination block dependent implementation. Saturnino's UX review, PR/hosted CI and project-owner M0 acceptance remain pending. Any later material change requires affected review to be repeated. Actual hours unknown.

## Completion addendum

- Browser retry completed all seven existing scenarios with no skips/failures. It exercised scan-gated extraction review; member/peer/private download and queued receipt scope; coordinator/admin scope; mobile accessibility; resumable/conflicting onboarding; coordinator creation/generation/approval followed by member submission, provisional grading and approved feedback; administrator routing.
- Automated accessibility checks passed on exercised screens. The generated mobile progress screenshot was visually inspected: labels/cards were readable, insufficient evidence displayed explicitly, N+2 safeguards explained and no visible horizontal clipping. This is a local browser check, not Saturnino's UX review or a full manual WCAG audit. [Retained screenshot](evidence/progress-mobile.png).
- Read-only Pint passed. Browser runner exited successfully and no listener remained on port 8123. No persistent worker/server is left by this task.
- Final validation checks all 37 section rows, all 15 requirement IDs, D01-D10, C01-C07, all eleven tickets, relative Markdown links, trailing whitespace and tracked-source preservation. Exact output is retained in [validation.json](evidence/validation.json).
- The manifest hashes the source PDF and every delivered file except the manifest itself. Source HEAD, local commit and those hashes identify the reviewed delivery. Required PR/hosted CI and human gates remain separate.
