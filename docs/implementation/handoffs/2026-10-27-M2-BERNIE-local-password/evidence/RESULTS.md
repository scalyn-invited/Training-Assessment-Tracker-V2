# M2 verification evidence

Date: 27 October 2026, Asia/Manila. Isolated worktree `codex/m2-local-identity`, base `6e2b9f5`; current changes are uncommitted. The final manifest identifies the reviewed source.

| Check | Actual result | Evidence / limitation |
| --- | --- | --- |
| Full SQLite regression, `php -d extension=gd vendor/bin/phpunit` | 190 tests, 4069 assertions; 186 passed, four utility skips | sqlite-regression.xml; before final narrow hardening/UI edits |
| Focused SQLite M2/M1 follow-up | 33 tests, 271 assertions, passed | sqlite-focused.xml; includes consent bypass/password-bound changes; later audit/client-selector changes verified in final MariaDB run |
| MariaDB M1/M2 permission and concurrency regression | 48 tests, 367 assertions, passed | mariadb-regression.xml; isolated MariaDB 11.4.13 on port 13327 |
| Final M2 feature + concurrency run | 16 tests, 128 assertions, passed | mariadb-final.xml; final source, includes duplicate creation, single-use setup, single invitation acceptance races |
| Additive M2 migration + rollback on disposable populated copy | 49 pre-existing tables preserved; no local passwords activated | Application source copy only; source SHA-256 below unchanged |
| Browser new account/invitation | Setup, login, wrong recipient denial, explicit acceptance, two-client choices, expired link passed | CLI returned seven successful scenario flags; phone width 390px had no horizontal overflow |

Four historical utility skips remain incomplete, not passed. Original application database SHA-256 before and after: `EE02294A6A20D7AD1BC03D92F5A5628843CBC10CEE22134936F26FEACD28BA33`.

## Failures and refinements retained

- Initial local-account run: 10 passed, one login Blade syntax failure; corrected, subsequently passing.
- Initial platform checks: eight passed, two GD-extension errors; enabled GD for qualification, subsequent tests passed.
- The first populated-copy rehearsal applied all pending legacy migrations and detected changed `groups` data. No source data changed. Repeated on a fresh disposable copy using only the M2 additive migration and rollback: all 49 tables preserved. This is not a full M1 application cutover rehearsal.
- A later in-flight test run observed the new view with its previously loaded controller and failed on clientChoices; it also hit a 15-second worker-start deadline under host load. Final stable source rerun used 60-second worker readiness and passed 16/16. No prior failed run is counted as a clean pass.
- Browser attempts first hit a label-locator timeout, an interrupted navigation/expired-page dialog, then a completed submission that exceeded the 30-second wait. Used the inspected combobox locator, explicit submission navigation waits, a fresh disposable fixture, and a longer allowance. Continued from the successfully issued link and verified the flows above. Earlier attempts remain failures, not additional successes.

Browser observations are local functional evidence. They do not establish Saturnino review, complete accessibility conformance, live identity, actual Cerebru integration, production readiness or project-owner acceptance.

Final-source SQLite M2 run: 13 tests / 108 assertions passed (`sqlite-final.xml`). Targeted Pint passed. Browser recovery follow-up returned five successful flags: password recovery, second-session revocation, own-password change, keyboard login, and mobile login without horizontal overflow. Desktop recovery and mobile login screenshots were visually inspected. Screenshot controls were then aligned with existing primary/secondary button classes; a final rendered check follows in the handoff. No separate Saturnino UX acceptance is claimed.

Final rendered check after applying existing button classes passed for login and recovery; updated screenshots are included. Final console contained only browser autofill suggestions, not application JavaScript exceptions. Task browser closed; ports 8157 and 13327 verified closed. No live services used.
