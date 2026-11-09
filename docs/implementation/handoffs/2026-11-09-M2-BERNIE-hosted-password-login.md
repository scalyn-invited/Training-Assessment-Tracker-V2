# M2 hosted password login integration

- Date: 9 November 2026, Asia/Manila. Contributor: Bernie, confirmed in conversation.
- Scope: commit existing hosted-password changes, rebase onto latest main and push to main, explicitly requested by Bernie. Deployment is separate.
- Revision: dirty `codex/m2-hosted-password-login` rebased onto `bce5271`; this handoff accompanies the resulting source commit.
- Changes: explicit hosted opt-in, secure configuration and HTTPS enforcement, console-only first-admin initialization, login states, tests and operator documentation. Existing local-password identities and permission checks retained. No migrations or live account changes.
- Preservation: all 64 pending files restored; 59 byte-identical and five equivalent after Git line-ending normalization. Recovery stash retained. Generated reports, browser logs/screenshots and Word lock file excluded from publication.

## Verification

Commands ran in `platform/` with PHPUnit's isolated in-memory SQLite database.

- `php artisan test --compact`: 220 passed, four skipped, seven failed, 4,469 assertions. All seven failures were image fixtures requiring the disabled CLI GD extension; hosted-password tests passed.
- `php -d extension=gd vendor/bin/phpunit --filter 'PlatformAdministrationTest|SavedBrandingTest' --testdox`: all 32 tests passed, 394 assertions, including all seven previously failing cases. GD enabled for this command only.
- Targeted `php vendor/bin/pint --test` on the six changed PHP files: passed. Unscoped Pint encountered an inaccessible pre-existing `.runtime/client-backfill/` directory.
- `git diff --check`: passed. Rebase and stash restoration had no conflicts.

## Review and remaining gates

Agent technical inspection covered opt-in checks, transport middleware, initialization guard and negative-path tests. This is not recorded human technical self-review. Bernie's human review, Saturnino's hosted-login UX/accessibility review and project-owner milestone acceptance remain unclaimed. Browser, MariaDB and hosted CI have not been rerun locally in this session. No live TLS/proxy, email, SSO/MFA or deployment qualification is claimed. See the operator guide for rollback by disabling hosted password access.
