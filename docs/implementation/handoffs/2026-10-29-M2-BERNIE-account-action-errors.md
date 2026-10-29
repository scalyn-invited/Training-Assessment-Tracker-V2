# Account creation denial diagnostics

Owner: Bernie. Task: M2-BERNIE/account-action-errors.
Branch: codex/fix-account-action-errors. Base: baaeaed757a2980271ba2991f2cbd2ee8f696c1b, with pre-existing uncommitted work preserved. This change is uncommitted; no remote publication.

## Evidence and scope

Read-only inspection found recent successful local-production sign-in followed by local.authorisation.denied. The actor has an active platform Admin grant; both current clients are active and unsuspended. Earlier audit records have no specific denial reason, so the original cause is not proven. No passwords were requested or inspected.

The approved diagnostic change adds fixed reason codes for ineligible accounts, unavailable local password identity, current-password mismatch, unavailable client, and missing administrator authority. Only authenticated local.actions responses display these fixed messages. Invitation and password-link responses retain generic text. The mutation audit stores a fixed reason code, not request data, passwords, or link tokens. Unclassified operation denials remain generic.

Permission conditions, HTTP 403 status, transactions, setup/invitation semantics and password verification remain enforced. The error screen now links back to account management. No real account creation, database migration, reset, email or external service call was performed. Pre-edit source copies are in platform/.runtime/account-action-errors-backup/.

## Verification

- LocalAccountsTest: 19 passed, 200 assertions, in-memory SQLite. New regression verifies explicit password/client denial messages, rollback/no account creation on denial, no password in response/audit/old input, and successful creation after correcting the inputs.
- LocalProductionTest: 4 passed, 57 assertions, disposable temporary SQLite. Covers ordinary account creation, permissions, setup/invitations, sessions and localhost guards.
- Pint check of the exception, service, bootstrap and account test: passed.
- Windows restricted access initially prevented the production-test process from opening dependencies; rerun with approved local access passed. A formatting write also failed; the import was corrected with the file-edit tool and the final formatting check passed.
- No browser visual QA or actual user retry captured. No claim that the original creation failure is resolved.

## Technical self-review and remaining gates

Bernie technical self-review: reviewed the bounded source changes for preserved access checks, rollback, credential-free messages/audit and response scoping. Prior unrelated changes remain intact. The new exception is a subtype of the existing HTTP exception, preserving existing denial handling. No schema or permission-contract change.

Next: user retries creation and reports the specific displayed message, or inspect the new fixed audit reason after that retry. Saturnino UX review and project-owner acceptance remain pending. No full M2 completion, hosted CI, PR, merge or deployment claimed.
