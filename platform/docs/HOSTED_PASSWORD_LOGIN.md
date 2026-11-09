# Hosted email/password login

This is the interim hosted authentication mode authorised by Bernie on 9 November 2026. SSO and MFA are not connected. This code change is not deployment approval or evidence of a live integration.

## Configuration

Set these values in the host's private environment configuration; use the actual HTTPS hostname:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://training.example.org
TRAINING_ENVIRONMENT=production
TRAINING_AUTH_MODE=local_password
TRAINING_HOSTED_PASSWORD_LOGIN=true
TRAINING_LOCALHOST_PRODUCTION=false
MOCK_IDENTITY_ENABLED=false
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Use the deployment's existing application key, designated production database and private storage. Do not copy local SQLite data, keys or test identities into a host. Keep SMTP and AI disabled unless separately authorised. No schema change is introduced by this slice; the existing application migrations are still required on a fresh host.

Hosted login is disabled by default. It also remains unavailable with debugging enabled, a non-HTTPS APP_URL, insecure or script-accessible session cookies, or SameSite outside lax/strict. HTTP requests are rejected before web/session handling. Configure the web server to redirect ordinary HTTP navigation to HTTPS before PHP and reject credential submission over HTTP. TLS must terminate at PHP or at an explicitly trusted reverse proxy: the app does not trust arbitrary forwarded headers. Proxy topology and trusted addresses require host-specific configuration and verification; do not trust all proxies to make login work.

After an authorised deployment/configuration update, rebuild the configuration cache using the host's established release process. Recheck /login over HTTPS and verify secure cookies, account login, logout, expiration and recovery on the actual host. Hosting, TLS/proxy configuration and live verification are not supplied by this change.

## Accounts and first administrator

Existing active, non-synthetic production accounts with an enabled local password and active local identity can sign in. Imported identities are not silently converted. Global Admins go to the platform workspace; other users go to client selection. Client/team permissions remain unchanged.

For a freshly migrated, empty production database only, an authorised operator can run:

```sh
php artisan training:initialize-production --name="Initial administrator" --email="admin@example.org" --operator="Named operator" --reason="Approved initial hosted account setup"
```

The output is a private, single-use setup URL that expires after one hour. Open it over HTTPS and enter the password privately. Do not place the URL in shared logs, tickets or chat. The command cannot replace an established administrator or recover a populated database. It may reissue the same pending first-admin setup, invalidating the earlier link. Existing admin-issued account setup/recovery and invitations remain available; there is no public registration or automatic email delivery.

Passwords retain the existing 15-character minimum and 72-byte maximum. Failed login remains generic; per-account throttling, route throttling, CSRF protection, session rotation, 30-minute idle/8-hour absolute limits, account deactivation and permission-version invalidation remain enforced.

## Rollback and future integration

Set TRAINING_HOSTED_PASSWORD_LOGIN=false and rebuild configuration through the release process to disable hosted password access. Existing password sessions are rejected on their next protected request. No account, password, identity or training history is deleted. The localhost production profile remains separate and loopback-only.

SSO/MFA require a separate implementation and qualification. Do not change TRAINING_AUTH_MODE to an unimplemented provider or treat password login as MFA. Authentication mode remains exclusive; disabling password mode does not enable a silent password fallback for a future identity provider.
