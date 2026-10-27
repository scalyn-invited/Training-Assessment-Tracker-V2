# Fictional training demo — sign-in and scenarios

Open http://127.0.0.1:8129/login and select a named identity. No password is required: this is the existing local mock sign-in, not live SSO. Every person, business, case, score and review is fictional. Email addresses end in `.invalid`; no external mail or paid AI runs. Ports8000,8123,8124,8125 and8129 now share this demo, following the user-approved cutover.

Three clients, 30 people, two internal teams and one training cohort per client. Each client has its own brand/default theme. The draft-programme member has a personal Neon Cyan override. Historical scenarios use reference14 September2026; draft calendars may need explicit rescheduling before approval today. Generated evidence is marked fixture-clean solely for this isolated demo; it is not antivirus qualification. Review scores are illustrative synthetic human decisions, not calibrated AI results or certifications.

## Accounts and training inventory

### Harbor Voice Support

Client Admin: **Maya Santos**. Coordinators: **Eli Cruz** and **Nora Reyes**.

| Member | Scenario | Primary coordinator | Enrolment |
| --- | --- | --- | --- |
| Lena Flores | onboarding | Eli Cruz | [Open](http://127.0.0.1:8129/enrolments/b05233bb-1cd4-58e0-8eb0-20b5bed0fe5a) |
| Owen Tan | draft | Nora Reyes | [Open](http://127.0.0.1:8129/enrolments/0dfa9aa1-8144-5ae4-869a-e61d0f2ecb4f) |
| Iris Lim | saved | Eli Cruz | [Open](http://127.0.0.1:8129/enrolments/434ee177-14a3-5401-8121-defaebc50215) |
| Noah Ramos | submitted | Nora Reyes | [Open](http://127.0.0.1:8129/enrolments/2916012f-ca9e-52a7-9b7b-cceb2c76bc39) |
| Ava Garcia | correction/resubmission | Eli Cruz | [Open](http://127.0.0.1:8129/enrolments/a83444bf-19ec-5420-a2a6-f47980c5b3a9) |
| Leo Mendoza | completed | Nora Reyes | [Open](http://127.0.0.1:8129/enrolments/920b8dce-0f00-52af-a682-331946888354) |
| Zoe Navarro | appeal | Eli Cruz | [Open](http://127.0.0.1:8129/enrolments/1a2bd921-4b72-5eca-aada-b695204473e1) |

### Northbridge IT Services

Client Admin: **Adrian Cole**. Coordinators: **Priya Shah** and **Marco Vega**.

| Member | Scenario | Primary coordinator | Enrolment |
| --- | --- | --- | --- |
| Ella Quinn | onboarding | Priya Shah | [Open](http://127.0.0.1:8129/enrolments/fe3b2314-8d12-5f84-bf96-609f6e309105) |
| Finn Parker | draft | Marco Vega | [Open](http://127.0.0.1:8129/enrolments/2690c9cd-a8b8-5ce0-a665-d4f472bd300d) |
| Isla Brooks | saved | Priya Shah | [Open](http://127.0.0.1:8129/enrolments/ccf3cdb6-ba68-5d52-8216-2b24f4996b12) |
| Theo Lane | submitted | Marco Vega | [Open](http://127.0.0.1:8129/enrolments/9459a10b-c190-5792-8eef-3e8af23703f8) |
| Mia Chen | correction/resubmission | Priya Shah | [Open](http://127.0.0.1:8129/enrolments/9f557121-c493-595e-aa2c-920e74e7de0e) |
| Jude Ellis | completed | Marco Vega | [Open](http://127.0.0.1:8129/enrolments/3522aff9-a584-57b3-a61e-2f9fa599262c) |
| Ruby Wells | appeal | Priya Shah | [Open](http://127.0.0.1:8129/enrolments/3d6e90aa-9126-5676-b968-19ad155ac51a) |

### Pixel Grove Studio

Client Admin: **Sofia Blake**. Coordinators: **Kai Morgan** and **Amara Reed**.

| Member | Scenario | Primary coordinator | Enrolment |
| --- | --- | --- | --- |
| Ben Foster | onboarding | Kai Morgan | [Open](http://127.0.0.1:8129/enrolments/37383650-07fa-5ead-8ce6-0f4d898368bd) |
| Chloe Hart | draft | Amara Reed | [Open](http://127.0.0.1:8129/enrolments/8ec473b1-8aac-5abb-9f6f-d3b5f4bfb52c) |
| Dylan Stone | saved | Kai Morgan | [Open](http://127.0.0.1:8129/enrolments/95b2f8ef-a989-53c9-942d-974e29afc5d3) |
| Eva Ross | submitted | Amara Reed | [Open](http://127.0.0.1:8129/enrolments/8ae93d15-7b31-52a2-a2ff-a89a3b679f55) |
| Hugo West | correction/resubmission | Kai Morgan | [Open](http://127.0.0.1:8129/enrolments/45dd9a10-fd8d-594b-9856-ef04bf056303) |
| Jasmine Ford | completed | Amara Reed | [Open](http://127.0.0.1:8129/enrolments/e604587a-bc25-5dfc-9a90-cbed107be9b8) |
| Luca Gray | appeal | Kai Morgan | [Open](http://127.0.0.1:8129/enrolments/d1babfcf-7170-52b7-9c19-35b77b2fbcef) |

## Access demonstrations

- **Lena Flores:** teamless in Harbor Voice, explicitly allocated to Eli Cruz; also belongs to Northbridge with an independent teamless allocation. Sign-in requires explicit client selection.
- **Owen Tan, Finn Parker and Chloe Hart:** members of both internal teams within their respective client; each has one primary coordinator.
- **Iris Lim, Isla Brooks and Dylan Stone:** active saved-work examples with one primary and an explicit backup. Backup status does not grant management or private-record access.
- **Noah Ramos, Theo Lane and Eva Ross:** submitted work; primary coordinator's sensitive grant was explicitly revoked after creation. Summary viewing remains scoped; sensitive material is denied.
- **Zoe Navarro, Ruby Wells and Luca Gray:** approved submissions under appeal, assigned to the other eligible team coordinator. Appeal authority covers its submission, not private reviewer files or unrelated records.
- **Leo Mendoza, Jude Ellis and Jasmine Ford:** completed programmes with all12 lessons approved and three on-target metrics, each with four approved observations.
- **Ben Foster:** departed Pixel Grove. Historical onboarding/evidence remains stored, but ordinary client access is denied. No departing-member history grant is fabricated.
- Clean member evidence and separate sensitive reviewer notes accompany each enrolment. Members cannot download the private reviewer file. No Global Admin grant is seeded.

## Reproduce safely

From `platform`, run `node scripts/realistic-demo.mjs --name=realistic-demo-newdemo --prepare` to create a fresh disposable copy. Start it with `node scripts/realistic-demo.mjs --name=realistic-demo-newdemo` only when port8129 is free; never stop another service blindly. Start the current five-port shared demo with `node scripts/demo-all-ports.mjs`. All five ports must be free; the launcher refuses to replace unknown listeners.

`node scripts/realistic-demo.mjs --seed` is an identical-rerun check, not a reset or repair. It refuses a changed source version, edited demo records, altered evidence or a partial previous build. Signing in adds an audit event, so a used demo may intentionally fail the identical-data check. Choose a fresh demo name instead. Never run migrate:fresh against the current application, Postman or8124 databases.

The seed manifest records the source hash, reference timestamp, scenario IDs, evidence hashes and completion snapshot. IDs and authored content are deterministic; password hashes/encrypted fields and infrastructure timestamps are not promised byte-identical between fresh databases. Private files, database queues/cache/sessions and cookie names are isolated. No queue worker is started; seeded notifications remain available in-app, and outbound learning notices stay contained.
