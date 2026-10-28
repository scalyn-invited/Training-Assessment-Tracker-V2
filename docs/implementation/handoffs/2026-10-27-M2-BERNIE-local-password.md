# M2-BERNIE local-password starter delivery

27 October 2026. Status: **ready_for_review for the local starter slice**; full M2 remains in progress. User explicitly authorised the detailed plan in this chat. Branch `codex/m2-local-identity`, isolated checkout `.runtime/m2-starter`, base `6e2b9f5`. Changes remain uncommitted; `manifest.json` in the companion directory records exact delivered hashes. Actual hours unknown.

## Delivered

- Local-only synthetic email/password authentication with explicit credential activation, immutable local identity, account/IP throttling, existing session limits and permission checks.
- Global/Client Admin account creation, one-hour single-use setup/reset links, current-password confirmation, authority rechecks, cancellation/reissue, secret-free audit and one-time link display.
- Seven-day recipient-approved Member invitations for existing accounts. Old direct person/join paths cannot bypass consent in local-password mode.
- Scoped recovery controls, Global Admin/self exclusions for Client Admins, password changes and resets that revoke every session, pre-client invitation access, guarded synthetic Global Admin bootstrap.
- Existing-layout login/setup/recovery/invitation/error screens and account controls; minimal presentation wiring, not Saturnino-authored design acceptance.
- [Contract](2026-10-27-M2-BERNIE-local-password/CONTRACT.md), [Cerebru discovery preparation](2026-10-27-M2-BERNIE-local-password/CEREBRU-DISCOVERY.md), [operations/reproduction](2026-10-27-M2-BERNIE-local-password/OPERATIONS.md), synthetic fixture helper, feature/race tests, and [UX review request](2026-10-27-M2-BERNIE-local-password/UX-REVIEW-REQUEST.md).

## Verification

See [full results and limitations](2026-10-27-M2-BERNIE-local-password/evidence/RESULTS.md). Full SQLite regression: 190 tests / 4069 assertions, four utility skips. Focused SQLite follow-up: 33/33, 271 assertions. MariaDB M1/M2 regression: 48/48, 367 assertions. Final M2 source: SQLite 13/13, 108 assertions; MariaDB 16/16, 128 assertions including three real process races. These are separate runs, not a repeated full suite after every edit.

Browser flows passed setup, login, wrong-recipient denial, explicit invitation acceptance, two-client selection, expired link, recovery, another-session revocation, own-password change and keyboard login. Desktop/mobile rendered checks are local evidence only. Earlier test/runtime/browser failures and subsequent corrections are retained in RESULTS.md.

The additive M2 migration and rollback preserved all 49 pre-existing tables in a disposable copy without activating passwords. The original database hash remained `EE02294A6A20D7AD1BC03D92F5A5628843CBC10CEE22134936F26FEACD28BA33`. It has pending historical M1 migrations; it was not migrated or used for test resets. The source checkout's four unrelated untracked paths were preserved.

## Technical self-review

Bernie-lane technical self-review covers the manifest revision, not independent human approval. Reviewed server-side client scope, current identity/permission checks, global-admin/self reset exclusions, activation versus membership timing, exact recipient consent, token hash/purpose/expiry/single use, issuer revocation, session invalidation, secrets/flash handling, transaction locking, legacy bypass paths and additive compatibility. Denial audit includes failed redemption without raw tokens. Browser-generated links stayed out of committed evidence; temporary access logs were redacted. No external endpoints, credentials or paid services were introduced.

## Remaining gates and next action

Saturnino's separate UX/accessibility review and project-owner acceptance are pending. Full M1 acceptance, application database cutover, PR/hosted CI and full M2 acceptance are not claimed. No push, merge, real-data access, SMTP delivery, deployment or live identity connection occurred. Authentik hosting/implementation remains deferred. Cerebru documentation/contact/sandbox are unavailable; discovery is prepared, not completed.

Next: review this exact isolated revision, obtain Saturnino's findings and project-owner local acceptance; supply authorised Cerebru documentation/contact for the next discovery slice. Preserve the isolated checkout until reviewed. Do not run these migrations or account bootstrap against the original application database without a separately approved cutover.

Cleanup: task-owned browser and MariaDB services stopped; ports 8157/13327 verified closed. Final login/recovery render check passed after existing button styles were applied. Original application database hash still matches. Generated browser session traces were removed from this worktree; safe screenshots and scripts remain in the companion evidence folder.
