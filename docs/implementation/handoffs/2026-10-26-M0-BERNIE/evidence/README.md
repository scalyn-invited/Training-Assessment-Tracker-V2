# Retained M0 evidence

Local execution on 2026-10-26 from `.runtime/m0-bernie-20261026/platform`. The parent verification record explains each result and limitation. Only synthetic test output/nonsecret configuration is retained; no `.env`, database, private storage, source assessment or credential is delivered.

- `php-approved.txt`, `php-results.xml`: fresh SQLite result and per-test evidence.
- `php-suite.txt`, `php-direct.txt`, `php-final.txt`: failed restricted-runner bootstrap attempts, not passing results.
- `setup.txt`, `setup-retry.txt`, `setup-final.txt`: failed environment-file/path attempts.
- `setup-clean.txt`: successful migrations and seed against new isolated database.
- `isolation.json`: resolved runtime paths, synthetic counts, test/mail/live-AI settings.
- `browser-suite.txt`: initial interrupted restricted run.
- `browser-approved.txt`: final retry results, classified in verification.
- `pint.txt`: read-only format-check result.
- `validation.json`: final document coverage/link/baseline checks.

Browser HTML reports, traces and screenshots remain in the ignored isolated runtime's `playwright-report/` and `test-results/`; source PDF render is in the parent runtime folder. Historical PR/CI outcomes in existing repository handoffs were read but not live-refreshed or used as current CI proof.

- `progress-mobile.png`: fresh browser screenshot visually inspected; local UI evidence only.
- `browser-last-run.json`: completed runner reports passed with no failed tests.
