# Local identity contract v1

Approved scope: 27 October 2026 conversation. Runtime qualification: local/testing, synthetic test accounts only. Technical integration contract; human UX and milestone acceptance pending.

Local password login uses email and an explicitly activated password, creates a server session and retains permission_version checks. Application-local identity is `urn:training:local` plus immutable user UUID. No external person linking, delegated token issuance or production mode.

POST /accounts/actions accepts action=create|setup|reset|cancel, client UUID where applicable, name/email for creation, target UUID for setup/reset, link UUID for cancellation, current_password and reason (8–2000 chars). Successful issuance renders the raw link once, never flashes it. Existing email produces an invitation, not duplicate account or membership. All failures omit secrets.

GET/POST /password/setup/{token} and /password/reset/{token}: single-use 1h token; POST password/password_confirmation, minimum 15 characters. GET does not consume. POST /password/change: current_password plus confirmed password. POST /invitations/{token}: authenticated intended recipient, explicit Member-only acceptance; expires in 7 days. Tokens stored as SHA-256 hashes and transactional consumption. Issuer permission version is pinned; any subsequent permission change invalidates outstanding authority.

New accounts receive Member membership after setup; existing users receive it after invitation acceptance. No automatic email verification claim. Email is trimmed/lowercased for lookup; ambiguous existing case variants fail closed. No access to other client membership lists is disclosed. Existing imported users are not silently converted to local credentials.

Local mutations serialize under the existing platform administration lock, then lock client/account/token records. Revocation and credential updates increment permission_version and delete all sessions. Setup/reset revokes outstanding password links; invitation acceptance invalidates sessions through existing membership services.

Synthetic scenarios: new account setup; existing multi-client invitation; wrong recipient; expired/replayed/reissued token; revoked issuer; protected Global Admin; invalid password; suspended client; parallel redemption and creation. Tests supply executable fixtures.

### M2 starter implementation decisions

Use the existing bcrypt driver with a 72-byte password ceiling so distinct suffixes cannot collapse to the same password. Initial setup reissue requires an explicit client; bootstrap is the only clientless setup path and grants no role. Client Admin recovery targets are scoped before displaying choices. Existing direct person-creation and platform join actions reject in local-password mode, preventing bypass of the new setup/invitation lifecycle. Imported permission freshness continues to apply. Member activation and invitation acceptance use existing membership/grant services under the transaction lock.
