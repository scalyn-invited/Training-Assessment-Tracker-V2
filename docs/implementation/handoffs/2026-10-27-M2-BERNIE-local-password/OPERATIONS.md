# Local M2 setup and qualification

Run only from this isolated checkout's `platform/`. No live deployment is enabled. Keep `TRAINING_ENVIRONMENT=test`, `APP_ENV=local` or `testing`, `TRAINING_AUTH_MODE=local_password`. `mock` remains the default for historical tests. Local-password mode rejects real/non-synthetic accounts and disables the mock selector/token service.

Dependencies are unchanged. PHP's GD extension is required by the existing branding suite. SQLite tests use `:memory:`; MariaDB tests require a dedicated instance with `training_foundation_test`, loopback connection, version 11.4.13 and `M1_EXPECTED_DATADIR` matching that instance. Never direct qualification at the application database.

## Browser reproduction

Create an empty `.runtime/m2-browser.sqlite` inside this checkout. Set DB_CONNECTION=sqlite, DB_DATABASE to its absolute path, APP_ENV=local, TRAINING_ENVIRONMENT=test, TRAINING_AUTH_MODE=local_password, SESSION_DRIVER=database, CACHE_STORE=database, MAIL_MAILER=array and APP_URL=http://127.0.0.1:8157. Set a local disposable APP_KEY. Run `php tests/Support/m2-browser-fixture.php`; it guards the exact disposable database path before resetting synthetic data. Serve using `php -S 127.0.0.1:8157 -t public scripts/browser-router.php`. The fixture password is the synthetic-only value in the helper. Do not use it for real accounts.

Use Playwright CLI with the supplied browser-flow.js. It keeps generated setup/invitation links in process memory and returns scenario booleans. Use a new isolated browser session, wait for form navigation, and keep the server on loopback. Never capture screenshots, traces or network logs of pages containing live setup/reset links. The development HTTP server may log token-bearing request paths: disable access logging for these routes or redact/discard that task-local log; no unredacted token logs belong in evidence. This is a prerequisite for any later hosting qualification.

Global Admin bootstrap: `php artisan training:local-setup USER_UUID --operator=OPERATOR_REFERENCE --reason="Initial synthetic setup"`. It only prepares an existing synthetic Global Admin with no active local password, creates no role, and prints a one-hour link once. It is not account recovery. Use only an isolated database. Existing random passwords remain inactive. Passwords are 15+ characters with a 72-byte bcrypt ceiling to prevent suffix truncation; no routine expiry or character-mix rules.

No email address is marked verified merely from possessing a manually shared link. Reissue initial setup from Account links for a pending account. Existing active accounts receive explicit invitations. A changed issuer permission version invalidates outstanding link authority, even if the issuer later regains a role. Lost access to every Global Admin requires separately authorised operator recovery.

Additive migration rollback drops the local-account link table and activation column; it does not undo client memberships or recover former passwords. A release rollback therefore requires a verified database snapshot. The isolated preservation rehearsal covered an unused M2 migration before any account flow; it is not a live restore proof.
