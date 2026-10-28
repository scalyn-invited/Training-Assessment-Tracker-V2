# Main M2 preview verification — 28 October 2026

Revision: codex/m2-main-local, base 6e2b9f5 plus uncommitted M2 changes. Runtime: main platform checkout, http://127.0.0.1:8000, synthetic platform/.runtime/m2-browser.sqlite.

Passed browser checks: account creation/setup, member login, wrong-recipient denial, invitation acceptance, two-client selection, expired link, recovery, other-session revocation, own-password change, keyboard login, mobile overflow, and member denial from platform administration. Desktop/mobile screenshots accompany this record. Automated keyboard and layout checks do not substitute for Saturnino's separate accessibility review.

Main focused tests: 33 passed / 272 assertions (in-memory SQLite). Original application database and 29 unrelated file hashes unchanged. The user authorised stopping the old grouped previews to free port 8000. M2 is left running. See the main activation handoff for credentials, startup and rollback.
