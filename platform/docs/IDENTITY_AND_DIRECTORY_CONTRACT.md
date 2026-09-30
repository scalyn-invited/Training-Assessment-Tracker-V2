# B01 identity and directory trust contract

Status: local contract harness implemented; live OIDC/MFA and primary-platform proof remain pending.

## Local identity

MockIdentity is intentionally not OAuth/OIDC and not a production JWT verifier. It uses a short-lived, application-key-signed sandbox envelope solely to exercise invalid signatures, issuer/audience/client, actor, scopes, expiry, permission versions and stale permission rejection. It is enabled only when APP_ENV is local/testing, TRAINING_ENVIRONMENT is test and MOCK_IDENTITY_ENABLED is true. Its users must be synthetic test users with a pre-linked active issuer/subject identity and active organisation.

The sign-in screen explicitly identifies the simulation. There are no passwords, MFA bypass, email-based identity linking or automatic administrator creation endpoints. Authenticated sessions bind the specific identity ID and permission version, rotate the session on login, and enforce 30-minute idle/eight-hour absolute limits. Disable/removal is checked on each protected request, including persistent Livewire requests.

Before production, replace this harness with a vetted OIDC client: exact redirects, authorisation code + PKCE, state/nonce, JWKS rotation and algorithm allowlists, issuer/audience/signature/expiry checks and tested MFA policy evidence. Prove revocation, recovery and IdP outage behaviour. Keep deployment disabled until this proof exists.

## Delegated questions

GET /api/v1/me/training and GET /api/v1/members/{memberId}/summary currently exercise mock delegated authentication and record filtering only. They never export synthetic/local-only profiles. Consequently the shipped sandbox yields an empty approved result set. This is not completion of the full OpenAPI response serializers, live delegated exchange or approved-fact integration (B07).

The production integration must resolve an authenticated actor AND calling client, use the training audience, intersect scopes with current record policies, enforce five-minute permission freshness and partition/invalidate retrieval caches. Directory machine credentials cannot stand in for the user. Arbitrary user headers are ignored. Add verified approved-version lineage and complete schema responses before enabling the external API.

## Directory adapter

training:directory-import accepts synthetic local JSON and an organisation UUID. It makes no network requests. It supports person.upserted, person.deactivated and changes to an already mapped membership, with event IDs, payload hashes, per-entity ordering, transaction locks and duplicate/stale rejection.

The membership fixture adapter currently takes a local group_id instead of the proposed group_source_id; this is an explicit sandbox mapping seam. Group upserts, new memberships, signed webhooks, source-ID group mapping, remote pulls, reconciliation cursors and promotion are pending. Directory person changes can update only linked primary_import records. A new imported person receives no identity or elevated role.

Deactivation and membership changes invalidate session rows and increment permission_version, immediately invalidating previously issued mock credentials. Member group removal also removes the coordinator's enrolment visibility. Local coordinator assignments are not derived from imported membership.

The real connector must validate inbound authentication/signatures, replay age, organisation/environment, exact payload schemas and source ownership before calling domain services. Advance reconciliation cursors only after persistence. Promotion requires duplicate review, verified mapping and stable idempotency; do not export history implicitly.

## Live integration checklist

- Confirm authentik product/instance, clients, approved MFA and recovery policy.
- Obtain primary directory API, immutable person/group IDs and sandbox.
- Agree delegated token exchange/assertion contract, JWKS and client/audience/scopes.
- Demonstrate permitted approved retrieval, denied peer/group reads and deactivation on real services.
- Test stale permissions, cache invalidation, key rotation, outages and reconciliation.
- Record actual results independently from the local harness tests.
