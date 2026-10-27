# M1 Bernie — Global Admin workspace

## Revision and scope

Implemented locally on `codex/m1-global-admin`, starting revision `bdd55830adaceb241d48c5965f3999a88ddd4a03`; working-tree changes are uncommitted. The user explicitly approved this implementation, shared navigation changes and the backed-up shared synthetic demo upgrade. No push, merge, deployment, real-account creation or live identity connection. Unrelated Word lockfile, prior browser output and `public/images/scalyn-logo.png` remain untouched. Source fingerprints are in `2026-10-27-M1-BERNIE-global-admin/source-hashes.json`.

## Delivered behaviour

- Dedicated platform Overview, Clients, Global Admins, Platform appearance and Administrative audit screens, with permitted sidebar navigation and existing responsive drawer. Global Admin sign-in opens `/platform` without a client selection; the ordinary client switcher remains explicit.
- Existing platform-scoped admin grants are reused. Bootstrap is an audited operator command for an explicitly identified existing active account; further appointments/revocations use the UI. Last-active-admin protection, actor permission locks, session/token invalidation and historical grants are retained.
- Client creation/name editing, explicit joining of existing accounts, Client Admin assignment/revocation, suspension/reactivation and optimistic versions. Suspension is client/environment scoped because legacy organisation rows have no environment field. Directory names shared with memberships in another environment cannot be silently renamed; the UI returns a coordination/reload message.
- Suspension blocks authority, ends sensitive and open appeal authority, cancels queued/running generation/extraction and queued notices/outbox/promotions, and cancels unfinished scans. Historical training and ordinary roles/memberships remain. Reactivation does not replay cancelled work or revive ended sensitive grants/appeal assignments. Notice delivery and scanning serialize against the organisation suspension lock.
- Platform display/application names, source colour, built-in default theme and re-encoded private raster logo storage. Platform logo is deliberately public on shared sign-in screens; client logos remain authenticated. Platform settings override deployment defaults, then client configuration/settings and personal themes take precedence. Shared screens use platform branding.
- Administrative audit is a dedicated environment-scoped ledger, with allowlisted fields and server-side filtering/pagination; no raw assessment/private payload display. Read-only operational indicators expose configuration flags, not credentials or claims of live integration.
- Additive migration `2026_10_27_140000_create_platform_administration.php`: platform settings/serialization row, client/environment state/version and platform audit ledger. Populated audit rollback is refused; restore a verified snapshot instead.

## Sign-in and operation

Open any of:

- http://127.0.0.1:8000/login
- http://127.0.0.1:8123/login
- http://127.0.0.1:8124/login
- http://127.0.0.1:8125/login
- http://127.0.0.1:8129/login

Choose **Alex Rowan — Fictional Global Admin**. This is the existing local mock sign-in, not a password account or authentik integration. The email is `global.admin@platform-demo.invalid`; there are now 31 fictional people. No client membership, sensitive grant or training role was added to Alex. Existing Maya/Adrian/Sofia Client Admin accounts retain their normal scope.

Platform workflows:

1. **Clients:** create/edit a directory, suspend/reactivate, search existing accounts. Explicitly join an account first, then grant Client Admin. Each step requires a reason and fresh account version.
2. **Global Admins:** appoint an existing active account or revoke a listed assignment. The last active administrator cannot be removed.
3. **Platform appearance:** save defaults/logo separately from client appearance. Failed saves retain prior values; concurrent changes require reload.
4. **Administrative audit:** inspect actor/operator, action, target, timestamp and reason. Filters use exact action/target values.

Operator bootstrap, only against an explicitly configured intended database/environment:

```powershell
php artisan training:global-admin <existing-user-uuid> --environment=test --operator="operator-reference" --reason="Documented appointment reason"
```

It rejects environment mismatch/inactive identities; an identical existing appointment adds no audit. It does not create identities and refuses a new bootstrap while a Global Admin already exists. Use the UI for subsequent appointments.

Guarded demo upgrade (backs up database/private files/manifest; never reseeds):

```powershell
node scripts/global-admin-demo.mjs --name=realistic-demo --upgrade
```

This extends an already complete isolated demo; changed/revoked extension authority is not automatically restored. The original base seeder's immutable snapshot remains historical; do not rerun it to replace the now-used demo. Start the five shared ports with the existing `node scripts/demo-all-ports.mjs` launcher.

## Actual verification

| Check | Actual result |
| --- | --- |
| Full SQLite run before final focused additions | 176 tests, 3,948 assertions, four skips, no failures (`full-sqlite.xml`) |
| Final affected platform/permission/delivery/scanner run | 29 tests, 216 assertions, one scanner-tool skip, no failures (`final-affected.xml`) |
| Final platform suite including friendly conflicts and failed-logo preservation | 10 tests, 98 assertions, all passed (`platform-final.xml`) |
| MariaDB 11.4.13 platform + concurrency combined | 22 tests, 171 assertions, all passed (`mariadb-complete.xml`); final presentation/failed-write test additions qualified separately on SQLite |
| Explicit suspension versus waiting programme approval | One test, six assertions passed; approval remained `needs_review` (`suspension-concurrency.xml`) |
| Final transactional current-read regression | MariaDB4 tests/24 assertions passed, including a Global Admin branding write waiting behind revocation, last-admin removal, duplicate grants and suspension/approval (`current-read-mariadb.xml`) |
| Final platform/permission regressions after current-read tightening | SQLite20 tests/164 assertions passed (`current-read-sqlite.xml`) |
| Formatting / syntax | `php vendor/bin/pint --test`, `node --check scripts/global-admin-demo.mjs`, `git diff --check` passed |
| Browser | Five platform screens at 1440×900 and 390×900: zero axe WCAG2A/AA/2.1AA violations and no horizontal overflow; screenshots included |
| Keyboard | Mobile drawer Escape closes and restores focus to Open navigation (`aria-expanded=false`) |
| Browser workflows | Mock Global Admin sign-in, client creation and platform appearance save passed on disposable port 8131; ordinary Maya received403 and no platform nav, retaining Harbor branding |
| Shared ports | Authenticated `/platform` heading visible on all five ports; each offers the same three clients plus Choose client option |

Commands were run from `platform/`. SQLite used `php -d extension=gd vendor/bin/phpunit` with the filters reflected above and `--log-junit` receipts. MariaDB used `phpunit.mariadb.xml`, dedicated `m1_qualification` credentials loaded privately, port13318 and `M1_EXPECTED_DATADIR` bound to `.runtime/m1-qualification-20261026/data`. No credential appears in this handoff. Concurrency uses independently released PHP workers, not SQLite concurrency as a substitute.

The four full-suite local skips were DOCX text/tables, DOCX external-relationship rejection, real PDF/image OCR and real ClamAV utilities. They are unavailable local tool checks, not passes. Hosted CI was not run or updated under this task's no-push boundary.

## Failures encountered and corrected

- Initial new platform tests passed5/62. Expanded SQLite run then exposed a fixture attempting to change the environment of an already-related user and a schema mock that lacked the new table lookup. Replaced the foreign user fixture, refreshed its database defaults, and updated the narrow schema mock. Final relevant checks passed.
- Initial MariaDB connection used the default root setting and was rejected for its authentication plugin. Re-ran with the existing dedicated qualification account; no authentication settings were changed.
- First combined MariaDB run had18 passing tests and one theme-concurrency fixture failure: its empty organisation had no explicit environment relationship. Registered the synthetic client in platform state. Four focused concurrency cases passed26 assertions; final combined22/171 passed.
- Disposable demo extension initially rejected a null password column; the account insertion rolled back. It now uses a random, unexposed placeholder hash with mock identity only. Rehearsal and identical rerun succeeded before the shared upgrade.
- First all-port browser measurement read counts before navigation finished; repeated with an explicit heading wait, confirming each rendered platform page. No application failure inferred from the premature measurement.
- Technical self-review identified that a MariaDB repeatable-read snapshot could predate a waited-on actor lock. Global Admin mutations now check current actor/identity state and use a locking current read of the role. Added an independent-worker regression that pauses a branding write behind revocation; final MariaDB4/24 and SQLite20/164 checks passed. MariaDB was briefly restarted for this check and shut down again afterward.

## Preservation and recovery

`preservation.json` independently confirms application and Postman domain-table hashes unchanged (48 and54 tables including SQLite bookkeeping; transient sessions/cache excluded). Every pre-existing shared-demo row is still present with the same values; only one user, identity and role were added to existing domain tables. All prior client branding hashes match.

Shared upgrade snapshot: `platform/.runtime/realistic-demo/global-admin-backups/2026-10-27T05-36-04-497Z/` contains `before.sqlite`, private files and manifest. Additional initial backups/evidence are under `platform/.runtime/global-admin/`. A disposable restoration to `restored-pre-upgrade.sqlite` passed SQLite integrity check and bidirectional row comparison with the pre-upgrade snapshot. No destructive populated-foundation rollback was attempted.

Temporary MariaDB13318, disposable preview8131 and task browser were stopped. Shared demo ports8000/8123/8124/8125/8129 remain available. Platform defaults on the shared demo remain unset: browser branding experiments were only on the disposable clone.

## Bernie's technical self-review and handoff

Reviewed migration scope, separate platform/client context, existing-account-only grants, last-admin serialization, actor-lock rechecks, client/environment suspension, audited state changes, logo publication/cleanup, scoped directory queries and audit projection. Global Admin status itself still provides no sensitive assessment/file authority. Existing sensitive self-grant rejection and primary-only approval policies remain in the regression set. The implementation does not claim that an external request already sent before suspension can be recalled; result access is denied and new work is cancelled.

Saturnino: separately review the five platform screens, active section, directory/account forms, suspension explanation, last-admin/conflict messages, audit filters, logo visibility and mobile navigation. Use the included desktop/mobile screenshots and shared synthetic login; automated checks are not your UX/accessibility approval. No edits were made to your record. Independent technical approval, project-owner acceptance, hosted CI, live identity/operations and full M1 acceptance remain pending.
