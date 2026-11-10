# Training Assessment Tracker V2

The runnable Laravel application is in [platform/](platform/README.md). Start there for the first-install browser wizard, MariaDB setup, password sign-in and test commands. The guide includes the upgrade procedure for existing SQLite clones and the protected HTTPS hosted installer.

- [Implementation rules](AGENT.md)
- [Two-person AI workspace and prompts](docs/implementation/README.md) — Bernie (Developer A and technical lead) and Saturnino (Developer B, UI and Design)
- [Current implementation status and next-step routing](docs/implementation/STATUS.md)
- [First milestone status](platform/docs/MILESTONE_01.md)
- [Onboarding and curriculum milestone](platform/docs/MILESTONE_02.md)
- [AI gateway and generation milestone](platform/docs/MILESTONE_03.md)
- [Requirements-to-code-and-test map](platform/docs/REQUIREMENTS.csv)
- [Original specification package](archify-upskilling-20260929-181205/inputs/00_START_HERE.md)

The application supports resumable onboarding, shared capacity calendars, queued mock curriculum generation, versioned editing and coordinator approval. The AI gateway includes two provider-specific adapters tested with simulated HTTP responses; live providers remain disabled. Local password authentication is available; live external identity/directory integration remains unconnected and notifications do not deliver email by default. This is not a live deployment or a completed learning platform.
