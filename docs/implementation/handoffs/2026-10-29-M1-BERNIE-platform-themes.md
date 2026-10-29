# Platform theme colours — implementation and verification

Date: 29 October 2026 (Asia/Manila). Contributor: Bernie. Status: ready_for_review.
Branch: codex/fix-platform-theme-colours. Base HEAD: baaeaed757a2980271ba2991f2cbd2ee8f696c1b. Changes are uncommitted alongside preserved prior work; no remote publication or deployment.

## Behaviour

Platform Light and Platform Dark use the existing accessible client palette generator and independent platform tokens. Platform appearance has both previews, both default options and explicit instructions: save a colour and choose a Platform theme to apply it. The five standard themes retain their palettes. Light cards intentionally remain neutral white, matching the existing generator; backgrounds, primary controls, navigation, text, focus and dark surfaces use generated colours. Previously unstyled platform action buttons now use the existing primary style.

Shared route branding is resolved centrally in Branding, including platform, sign-in, client selection and account-link/password screens. The view composer already isolated the principal platform screens; this change removes its null-user workaround so signed-in platform preferences can be scoped by user and environment without inheriting a client. Client configuration/defaults/personal choices remain authoritative inside client workspaces. Unconfigured clients inherit platform defaults. Existing client preference keys are unchanged. Former shared-browser preferences are copied once into the new platform key; Use platform default resets without reimporting the old preference on reload.

No database migration. The existing appearance endpoint additionally accepts platform-light/platform-dark. Existing defaults, audit, permissions, validation and optimistic version checks are retained. The active application's saved branding was not edited; activation requires selecting a Platform theme and saving. Refresh the browser to load updated assets.

## Verification

Final command from platform/: `php -d extension=gd vendor/bin/phpunit --filter='PlatformAdministrationTest|AutomaticClientThemeTest|BrandingTest|ThemePreferenceTest|LocalAccountsTest|LocalProductionTest|ClientFoundationTest' --do-not-cache-result --log-junit .runtime/platform-theme-tests.xml`.

**76 tests / 3,545 assertions passed.** In-memory/disposable databases only. [JUnit](2026-10-29-M1-BERNIE-platform-themes/tests.xml). Coverage includes existing generated-palette contrast checks, both platform palettes, saved/default/personal precedence, zero/one/multiple-client platform context, custom client branding isolation, invalid/stale saves and audit events. Existing default, account and localhost-production tests pass. Scoped Pint, JavaScript syntax and git diff whitespace checks passed.

Chrome via Playwright CLI on disposable port 8137 verified:

- Unsaved previews change only the preview; reloading discards them.
- Orange Platform Light and blue Platform Light update actual computed primary/button/navigation/background colours; neutral light cards match the generator.
- Saved colour survives reload; Platform Dark uses its dark palette; a personal platform choice survives reload.
- Use platform default restores the saved choice. Legacy preference migration works and reset remains effective on reload.
- Standard Light, Dark and Neon Cyan retain their original computed primary values.
- An unconfigured client inherits Platform Dark, uses a separate preference key, and preserves its own Neon Emerald choice when returning from platform pages.
- Shared sign-in inherits Platform Dark. Mobile 390 x 844 has no horizontal overflow.
- Desktop and mobile screenshots were visually inspected. This is focused visual/functional verification, not a full accessibility audit.

Screenshots: [orange desktop](2026-10-29-M1-BERNIE-platform-themes/orange-light-desktop.png), [blue dark desktop](2026-10-29-M1-BERNIE-platform-themes/blue-dark-desktop.png), [blue dark mobile](2026-10-29-M1-BERNIE-platform-themes/blue-dark-mobile.png).

Browser-check scripts and fixture/launcher copies are in the evidence directory. Restore them under platform/.runtime/platform-theme to rerun; launcher recreates only its guarded disposable test.sqlite. Synthetic credentials only. No email or live AI. Browser and test server were stopped; the active application server was not restarted.

Initial failures were resolved: restricted dependency access prevented PHPUnit bootstrap (approved local execution fixed this); platform previews needed a view-composer binding; a direct branding test needed an actual client-page request after login; browser command quoting and dropdown selection required correction. Browser testing exposed unstyled platform buttons, fixed above. A browser expectation was corrected because the shared generator deliberately keeps light cards white. These are distinct from the final passing results.

## Preservation, review and remaining gates

[Source hashes](2026-10-29-M1-BERNIE-platform-themes/source-sha256.json) record before/after versions of this task's ten source/test files. Pre-edit copies remain under platform/.runtime/platform-theme-backup. Overlapping files retain prior M2/M3/client-selection changes; do not reverse the whole branch or replace files wholesale when rolling back. Revert only this task's changes relative to those pre-edit copies. No schema/data rollback is needed. Other source files were not edited; Bernie's record has append-only task entries.

Bernie technical self-review (agent-assisted, not independent approval): reviewed theme token separation, shared-route/client isolation, preference migration/reset, unchanged save authorization/auditing/version checks and the incremental edits against pre-edit copies. Saturnino UX review, project-owner acceptance, PR publication and hosted CI remain separate and unclaimed. No hours are inferred.
