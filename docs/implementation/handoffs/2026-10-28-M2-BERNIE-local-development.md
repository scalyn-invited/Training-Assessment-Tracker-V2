# M2 local-development accounts — 28 October 2026

Branch: `codex/m2-local-development`, base `6e2b9f5`, plus preserved uncommitted M2 work. User explicitly authorised the local-development plan. Status: local account changes ready for review; external-service activation blocked on provider/data/cost decisions. No deployment or full M2 acceptance claimed.

## Delivered contract

- Ordinary email addresses are accepted for application-local accounts. The creation form has a checked-by-default Test account checkbox. The service accepts an optional final boolean (default true); HTTP `is_synthetic` accepts a boolean and defaults to true when omitted. Existing-account invitations never change classification, identity, name or history.
- Non-test accounts can complete setup, log in, recover/change passwords, and accept Member invitations. Eligible local identities appear in recovery selectors. Imported identities are not automatically linked or activated.
- APP_ENV local/testing and TRAINING_ENVIRONMENT test remain required. The existing schema's test environment is an isolation partition, separate from the per-person classification. Production password access stays blocked. Existing operator bootstrap remains synthetic-only; it is not expanded into a real-account recovery bypass.
- Security rules, current-password checks, audit reasons, client isolation, session limits/revocation, explicit invitation acceptance, throttles and token expiry are retained. No schema migration, data reset or account conversion occurred.

## Email preparation and activation gate

New optional HTTP `send_email` requests synchronous SMTP delivery after successful issuance. The normal one-time private-link result remains available. No new routes or queue jobs. Setup/reset/invitation URLs exist only in request memory and the mail message; no persistent raw-link queue payload or application audit field is introduced. Exceptions are caught without logging their potentially sensitive contents. A transport acceptance message is not proof of inbox delivery; failure leaves the link valid and grants no membership.

Defaults deny sending. Activation requires all of: `TRAINING_SMTP_ENABLED=true`, `LOCAL_ACCOUNT_MAIL_ENABLED=true`, `MAIL_MAILER=smtp`, valid SMTP/sender configuration, and the exact recipient in comma-separated `LOCAL_ACCOUNT_MAIL_RECIPIENTS`. This initial deny-by-default recipient list applies to both test and non-test accounts pending the user's recipient-policy decision. Log/array mailers are never used to emit account secrets. A checkbox appears only when SMTP account delivery is configured. Credentials stay outside version control.

Outstanding: provider, sender/domain, approved recipients and credentials. No external email was sent. Links currently point at loopback and must be opened on this workstation; remote onboarding is outside this local slice. When configuring mail, use a bounded SMTP timeout and do not attach message-body logging or persistent diagnostic listeners.

## AI preparation and activation gate

Anthropic/Gemini configuration now reads explicit provider enablement, model, data classes, micro-USD/token input/output rates and rate-version values from environment configuration. All providers remain disabled by default, in addition to the existing global live switch. Existing routing, data-class checks, per-organisation/programme budget enforcement, pinned endpoints, failure handling and accounting remain enforced.

Outstanding: provider/model, validated rates, spending policy, approved data classes and credentials. Existing live adapters reject synthetic training content; enabling live AI does not relax that policy. Non-test accounts cannot use the synthetic mock generator. No live model was selected, no paid calls were made, and no monthly-budget guarantee is inferred from the existing organisation/programme caps.

The launcher no longer unconditionally overrides SMTP, mailer or live-AI choices. It reads normal local configuration on restart. Current configuration and the running process still have email/live AI disabled. No scheduler or queue worker was started. Authentik/Cerebru remain deferred.

## Verification and technical self-review

- Final command from platform: `php -d extension=gd vendor/bin/phpunit --filter='LocalAccountsTest|AiProviderTest|AiGenerationTest|ClientPermissionIntegrationTest|PlatformAdministrationTest' --log-junit=../.runtime/m2-main-activation/local-development-tests.xml`.
- Result: **59 tests, 418 assertions passed**, forced in-memory SQLite; provider HTTP calls mocked. XML in this handoff's companion directory. Initial run had two pre-existing image tests blocked by disabled GD; enabling the installed extension resolved them. An initial new-test session setup was corrected to explicitly log out before changing actor.
- New coverage: ordinary emails, default/explicit classification, non-test setup/login/recovery/change/invitation, unchanged classification for an existing-account invitation, session invalidation, Member-only access, recipient-denied/disabled email, SMTP acceptance and sanitised failure, no queue jobs/raw audit tokens, and consumed-link rejection. Existing permission, budget, timeout and provider-failure tests passed.
- Browser on the running main checkout: checked Test account default; toggled it by keyboard; created a non-test-classified fixture with reserved example.org email; completed setup and login; selected Training Sandbox; received 403 at platform administration. Desktop/mobile screenshots captured; no horizontal overflow at 390px. Fixture contains no real-person data. Browser trace files were removed because they can contain private-link paths; QA browser closed, server left running.
- Screenshot review: new form labels and checkbox render correctly. The existing mobile navigation/skip-link presentation still needs Saturnino refinement; this is not a complete accessibility approval. Screenshots predate the final overview label change from qualification to local development.
- Technical self-review: eligibility no longer requires synthetic status; environment and active identity checks remain enforced; no external identity creation by email; imported freshness checks unchanged; issuance remains transactionally separate from SMTP; delivery failure never consumes the token. No live-send side effects in tests.
- Original database SHA-256 unchanged: `EE02294A6A20D7AD1BC03D92F5A5628843CBC10CEE22134936F26FEACD28BA33`. All 29 original unrelated file hashes unchanged.
- MariaDB/concurrency and hosted CI not rerun for this follow-up. Earlier M2 concurrency evidence remains historical. Saturnino review and project-owner acceptance pending.

## Runtime and rollback

URL: http://127.0.0.1:8000/login. Active database: `platform/.runtime/m2-browser.sqlite`; original `platform/database/database.sqlite` untouched. Existing demo credentials still work. Restart from platform with `powershell -ExecutionPolicy Bypass -File scripts/serve-m2-demo.ps1` after checking port ownership. Restart is necessary to pick up future changes to inherited environment variables; no restart is needed for the account code already verified in the running preview.

To disable outbound capabilities, keep/reset the three service-enable flags to false and restart only this preview. To restore prior local configuration, follow the main-activation handoff using `.runtime/m2-main-activation/original.env`; verify process ownership before stopping it. Preserve this separate database and any added accounts. Never use a broad Git reset or rerun the destructive fixture seeder for rollback. If reverting the account changes, non-test local accounts will cease to authenticate; retain their data for an explicit follow-up decision.

The companion manifest.json records SHA-256 hashes for the 13 changed application/configuration/test files in this follow-up.
