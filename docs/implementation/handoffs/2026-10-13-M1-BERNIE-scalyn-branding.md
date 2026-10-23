# M1-BERNIE/scalyn-branding

Date: 2026-10-13. User: Bernie. Status: ready_for_review; peer acceptance pending.

User supplied Downloads/scalyn opc logo.png and requested Cerebru -> Scalyn and coordinated colours. Copied the supplied PNG unchanged to public/images/scalyn-opc-logo.png. Default deployment uses existing white-label mode, Scalyn name, supplied logo on signed-in/signed-out pages and favicon; Cerebru attribution/link hidden. Other deployment modes remain available. Navy #1d4668, sky-blue accents and pale blue Light surfaces replace green. Logo has a white backing for dark themes.

Isolated candidate .runtime/cerebru-ui; source baseline .runtime/scalyn-branding-20261013. Ten delivered files verified by SHA-256 in dated evidence directory. No schema, provider, permission or source database change. No commit, push or deploy.

Checks: BrandingTest 5 passed/50 assertions; white-label preview browser scenario passed; final all-five-themes browser scenario passed (54.4s), including persistence, reset and automated contrast checks. Earlier runs exposed click handlers binding only after DOMContentLoaded: delegated choice and reset handlers now attach immediately. Same assertions/timeouts retained. Logs preserve earlier failures and final success. node --check public/appearance.js and git diff --check passed. Existing browser fixtures updated for Scalyn/default palette. Full workflow suite was not rerun for this narrow change.

Desktop Light and Dark reviewed live, final Light screenshot reviewed. Browser control later lost its debugger connection; saved automated screenshot is the final visual evidence. Test servers 8143–8146 stopped after validation; preview 8140 retained. Backup preserves previous local changes. Peer/M1/production gates remain unchanged.
