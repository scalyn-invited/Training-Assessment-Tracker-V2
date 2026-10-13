# Cerebru companion UI and branding

Implemented locally on 12 October 2026. Reference: https://opsbrain-eosin.vercel.app/home (sidebar, compact typography, panels and five-option theme menu inspected). The tracker remains a separate Laravel/Blade application. No Cerebru identity API, cross-origin preference sync or deployment is implied.

Updated 13 October 2026 to follow https://upskill.bernie.newwebsite.live/workspace: ivory/forest-green Light palette, account header, spacious navigation, serif hero and account/journey cards. The reference was inspected in the browser. Existing route and permission boundaries are retained; reference-only skills/open-weeks screens are not invented.

## Appearance

Overview, learning plans, updates and permission-appropriate management links share the sidebar. At widths up to 800px the sidebar becomes a keyboard-accessible drawer. Existing forms, tables, reviews, notifications, onboarding and learning screens use shared theme tokens.

Available themes: Light (default), Dark, Neon Cyan, Neon Emerald, Neon Amber. The matching names/options are verified; neon palettes are local implementations, not imported Cerebru source tokens. Preferences are saved in localStorage using a hashed organisation/environment/user key. Only the theme identifier is stored; no credentials or learning data. Browser storage denial falls back to the workspace default. The early script applies the preference before CSS renders. Preferences are independent of Cerebru and other devices.

Current deployment defaults to Scalyn white-label branding using the user-supplied scalyn-opc-logo.png, including the favicon. Light uses navy (#1d4668) with sky-blue accents on a pale blue background. The full-colour logo sits on white for dark-theme readability. Cerebru and co-branded modes remain configurable for other deployments; the current default hides Cerebru attribution and the parent link.

## Deployment branding

Edit config/branding.php through the normal code-review/deployment process. Defaults apply to signed-out pages. Optional overrides use exact existing organisation UUID and environment keys, resolved exclusively from the authenticated user. This is an adapter for the current organisation model, not acceptance of the future client/grant contract. There is no query-string client selector, domain resolver or schema migration.

Supported modes:
- cerebru: Cerebru identity with the training application subtitle and parent link.
- co-branded: client identity plus Part of Cerebru attribution and parent link.
- white-label: client identity and application name; no automatic Cerebru attribution or parent link.

Fields: app_name, client_name, logo_light, logo_dark, compact_logo, favicon, accent_light, accent_dark, default_theme, footer and cerebru_url. See examples/branding.php. Curated images must exist under public/images and use PNG, JPEG, WebP or SVG; remote URLs and traversal paths are rejected. SVG assets must be reviewed by the deployer; this is not an upload endpoint. Text is escaped by Blade. Colours accept only six-digit hex. The parent link accepts HTTPS URLs without embedded credentials. Missing/invalid assets fall back to the neutral training mark. One logo variant falls back to the supplied other variant. Ensure supplied logos are readable in both palettes.

The admin Appearance page previews client/co-branded/white-label identity without persisting changes. Preview text never modifies the actual header or another user’s identity. A saved client-admin editor, upload validation pipeline, custom domains and pre-login tenant identification await accepted client contracts and are not implemented here.

Brand accent is decorative; theme tokens retain readable controls and status text. The logo, application name, page title, favicon and footer carry deployment identity. Admin-only previews can show Cerebru as a selectable branding mode even in a white-label deployment; member screens do not expose that editor.

## Verification and rollback

Run PHP branding/foundation checks, Blade compilation and the browser appearance/foundation/learning suite in an isolated synthetic environment. TRAINING_BROWSER_PORT overrides the browser suite port; its fixed .runtime/browser.sqlite is relative to that isolated application checkout. Never run fixture resets on a shared database.

Restore the changed view/static/config/provider/route files from a reviewed prior revision to roll back. No database migration or backfill is involved. Remove the unused appearance route if rolling back only the layout. Increment static asset versions after subsequent CSS/JS changes. Existing Scalyn assets remain available for configured client branding.

Peer acceptance, full multi-client isolation qualification and production acceptance remain separate gates. See the dated Bernie handoff for executed checks and limitations.
