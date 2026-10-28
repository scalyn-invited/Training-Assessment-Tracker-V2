# Cerebru discovery — M2 starter

27 October 2026. Preparation only. No documentation, contact, sandbox or credentials supplied. No external requests, invented endpoints, or live qualification claimed. This continues the M0 contracts-discovery checklist.

| Evidence needed | Responsible party | Current evidence / exit condition |
| --- | --- | --- |
| Authorised Cerebru contact and product owner | Project owner | Unavailable; name and authority required |
| Versioned API/identity documentation | Bernie obtains from authorised contact | Unavailable; retain source URL/version and supported operations |
| Sandbox URL, access scope, synthetic-data policy | Authorised contact | Unavailable; verified environment and permitted test actions |
| Authentication, issuer/audience, MFA, keys/rotation | Bernie | Unavailable; actual contract, no assumed authentik connection |
| Delegated actor versus directory machine credentials | Bernie | Unavailable; prove actor/caller separation and revoked/forged denial |
| Immutable person/client/team IDs and duplicate mapping | Bernie | Unavailable; explicit mappings, no name/email identity merge |
| Source-owned fields versus local training ownership | Bernie + product owner | Unavailable; field-level ownership and conflict rules |
| Pagination, cursor commit, events, versions and ordering | Bernie | Unavailable; example pages, atomic cursor and replay cases |
| Deletion/deactivation, tombstones and permission freshness | Bernie | Unavailable; revocation timing and out-of-order removal tests |
| Rate limits, error schemas, retry and ambiguous requests | Bernie | Unavailable; original-key reconciliation evidence |
| Safe entry/return navigation and client context | Bernie; Saturnino UX | Unavailable; approved destinations, no identity inferred from URL |
| API/SSO licensing, entitlements and operational ownership | Project owner | Unavailable; costs and data permissions require actual approval |

Start implementation of a live adapter only after the relevant contract is verified. Record dated evidence, revision, expected/actual result and remaining gaps. Keep directory mocks labelled local. Authentik hosting and implementation remain deferred; local passwords do not establish Cerebru delegation or production identity readiness.
