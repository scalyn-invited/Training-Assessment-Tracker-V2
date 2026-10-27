# M1 client permission API checks

Import `m1-client-permissions.postman_collection.json` and `m1-client-permissions.postman_environment.json`. The collection uses Postman v2.1. Every environment value is deliberately empty.

Use only an explicitly configured disposable synthetic database and local preview. Set `baseUrl`, the authorised `clientId`, an unrelated `foreignClientId`, the allocated `memberId`, and a `foreignMemberId`. Never point this rehearsal at the existing application database.

Authentication is the **existing local sandbox signed delegation mechanism**, not authentik, OIDC or a production token service. In a console already bound to the disposable database, the existing `App\Services\MockIdentity::issue($syntheticUser)` issues a five-minute token. Use a freshly loaded, active synthetic coordinator for `token`. For `changesToken`, use the same helper with `['scope' => ['training.changes.read']]`. Keep tokens local; do not save or commit populated environments.

- For `staleToken`, capture a token, then use the administration service to revoke a role, allocation or sensitive grant. Its permission version must become stale.
- For `revokedToken`, capture a token, then explicitly end that user's client membership through the administration service. Do not manipulate legacy fields to simulate revocation.
- Reissue ordinary tokens after the five-minute lifetime. An expired token cannot establish the intended foreign-client or permitted-access case.

The eight requests cover authorised self/member summaries and the change feed, missing/foreign clients, a foreign member, and stale/revoked tokens. Synthetic training is intentionally omitted from export responses, so a permitted summary can return an empty `items` array. Client selection for these API calls is **always** `X-Client-Id`; browser session selection is not an API fallback.

Validation performed: JSON parsing, v2.1 collection shape, eight requests, declared placeholder references and empty environment values. Equivalent endpoint authorization is covered by PHP feature tests. Import into the Postman desktop application and collection execution were not performed.
