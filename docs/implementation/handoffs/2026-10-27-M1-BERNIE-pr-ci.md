# M1-BERNIE — combined PR, Scalyn integration and CI

The user explicitly authorised publishing the combined M1 implementation, opening a draft PR and running GitHub CI. They then selected: keep Scalyn branding/themes and integrate the sidebar.

Combined implementation commit: `c79ca5baec40feeb0bca8a2955bfa8627f700d3f`, based on `418dd7f`. Latest main `9d794e0` contains the previously merged Scalyn presentation. A local integration merge preserves that work; this does not merge the proposed PR into main.

Resolved the six overlapping areas: provider registrations, browser configuration, shared stylesheet, dashboard, shared layout and routes. Retained branding assets, all five themes, Appearance and existing navigation. Added active-client selection and scoped menu/preview gates. Branding and theme preferences now bind to server-validated client context, with no authority from legacy organisation/role fields. Mobile focus trapping, dismissal, inert background and resize cleanup remain in the dedicated navigation script; removed the duplicate navigation controller from appearance.js while preserving theme behavior.

The user-selected Scalyn presentation supersedes the green-only screenshots and source manifest in the earlier UI handoff. Earlier validation remains historical; the integrated revision requires fresh evidence.

Local integration PHP: 59 tests / 353 assertions passed. The expanded 16 browser scenarios are running; hosted CI is pending publication. CI now includes mapping safeguard tests, JS syntax checks and the dedicated isolated UI fixture, with browser artifacts uploaded. Mapping Python tests: four passed. Initial optional YAML parse was unavailable because the local Python runtime has no PyYAML; no installation was performed.

The initial Git push waited in the credential manager and was cancelled before a remote branch appeared. Publication uses the existing authenticated gh credential helper. No secrets, databases, Word lock files, CLI browser artifacts or unrelated untracked logo were selected for commit. No existing application/Postman DB migration, merge of the PR, deployment, live identity or paid service is authorised.

Saturnino's separate UX/accessibility review and full M1/project-owner acceptance remain pending. Final PR link, commit and CI outcomes follow below when verified.
