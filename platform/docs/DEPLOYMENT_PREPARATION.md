# Deployment preparation and release gates

The user confirmed that no live identity provider, primary API, AI provider, SMTP service or host is ready. These artifacts prepare a deployment; no host has been deployed or qualified. Live OIDC/MFA and delegated-token verification are still unavailable. The sandbox verifier is explicitly disabled outside local/testing test scope. Production must remain closed until the real contracts and adapters are implemented and verified.

## Linux VM

Use a dedicated application account and separate MariaDB database/user. Serve only `platform/public`; keep `.env`, private storage, source, backups and logs outside the document root. Templates in `deploy/` provide Nginx, a supervised bounded queue worker and a one-minute scheduler timer. Replace placeholder paths, TLS host/certificates and PHP socket after host qualification. No Node server is needed at runtime.

Install locked Composer dependencies, and verify PHP CLI/FPM parity, supported patched versions and extensions (PDO MySQL, mbstring, fileinfo, OpenSSL, XML/DOM, cURL, ZipArchive for DOCX). Qualify ClamAV and its signature-update process. Configure owner-only `.env`: APP_DEBUG=false, HTTPS APP_URL, secure/HTTP-only/SameSite cookies, MariaDB, database sessions/cache/queues, retry_after=180, explicit environment and separate secrets for each service. Do not use the workstation's old PHP build as a production image.

Release sequence after gates are signed: maintenance mode; stop/drain workers; take and verify encrypted database/private-file/key backups; review `php artisan migrate --pretend`; run `migrate --force` once; clear/rebuild configuration/routes/views; restart the same-release workers; smoke-test role boundaries, private downloads, submissions, jobs and the scheduler; then reopen traffic. Never use migrate:fresh on operational data. Restart workers after every code/config change.

For rollback, prefer a compatible code rollback or restore the complete pre-release snapshot into isolation. Do not blindly run migration down commands after real activity: capacity schedule revisions can contain multiple historical reservations for a programme/date, and the older unique constraint cannot represent them. Preserve newer data, approved decisions and the latest deletion ledger before a restore.

## cPanel qualification

Do not approve cPanel from a marketing feature list. Demonstrate PHP CLI/FPM extensions and version parity; a private writable storage directory outside public_html; HTTPS/secure sessions; a real database; required outbound TLS; 90-second provider calls; 120-second worker jobs below retry_after=180; lock-capable database cache; and a cron process that runs every minute. If persistent workers are prohibited, qualify non-overlapping `queue:work --stop-when-empty --max-time=50 --timeout=120 --tries=1` invocations under a host-provided lock and confirm the host permits an in-flight job to finish. If job/process limits are shorter, use the VM. Do not silently reduce timeout while enabling ambiguous paid calls.

## Monitoring and recovery

`php artisan training:heartbeat` records scheduler activity and queues a worker heartbeat. The scheduler invokes it each minute. `training:health` emits database status, worker/scheduler age, queue depth/oldest job, failed jobs, pending reviews, held AI/SMTP outcomes, spend and free disk without evidence text. It exits nonzero for stale components or failed jobs. `launch_ready` stays false because live gates are unresolved.

Assign named owners for identity/sync, application/queue, review backlog, AI budget, SMTP, security/privacy and backup recovery. Alert on heartbeat age over five minutes, queued work older than the agreed SLA, ambiguous calls, failed jobs, nearing AI budgets, low disk, failed/old backups, missed review cutoffs and long review backlog. The provided report is a polling surface; no external alert recipient or service has been configured.

Pending ledgers are recovered by `training:outbox`, `training:ai-recover`, and `training:notices`. Unknown provider or SMTP outcomes are not automatically resent. Known SMTP 4xx rejections have at most three retries; 5xx failures stop. Recheck current access before each send. SMTP acceptance and delivery/bounce reporting remain distinct; actual provider delivery/bounce callbacks require the selected service's authenticated contract.

## Backups and restore

`deploy/backup.sh` is a Linux template for a maintenance-window, worker-stopped MariaDB dump plus private files and `.env`, encrypted with an age public recipient. Credentials live in an owner-only MariaDB client option file, never command arguments. Store the private age identity separately/offsite. Include a separately managed IdP configuration/key backup. Keep the post-backup deletion ledger exported securely after each purge. Upload encrypted archives to independently retained off-host storage and alert on failure; offsite transport is not configured here.

Production restore drill: create an isolated host/database with outbound AI/SMTP/integration disabled; verify archive checksum/authentication; restore database/private files/application key/IdP configuration; merge the newest deletion ledger and run `training:reapply-deletions`; verify file hashes and permissions; inspect pending/ambiguous ledgers before controlled replay; prove duplicate delivery suppression; time the recovery and compare the agreed RPO/RTO. Never replay unknown external sends simply because a database was restored.

`php scripts/restore-drill.php` actually executes a separate synthetic SQLite drill under `.runtime/restore-*`, with authenticated encryption, tamper rejection, row counts, file hashes, a post-backup deletion and duplicate outbox replay. It never accepts a production path. It uses a synthetic key/mock IdP marker and does **not** prove MariaDB/real-key/IdP/offsite recovery. Drill artifacts are ignored; remove only verified drill directories when no longer needed.

## Retention

`training:retention` defaults to dry-run. `--apply` additionally requires TRAINING_RETENTION_APPROVED=true, which defaults false. The implemented policy targets evidence objects older than 24 months, excludes active legal holds, records a durable deletion ledger before physical removal and supports repeatable deletion after restore. It does not claim organisation-wide subject erasure or purge all identity, assessment, grade and audit records. Obtain the actual policy before enabling it. Temporary terminal AI input/output is pruned after 30 days; ambiguous calls and held enrolments are retained for resolution/legal review.

Legal holds are created through the audited Operations service. A production hold-release/subject-erasure workflow and jurisdiction-specific retention approval remain separate requirements; never infer consent or legal authority from this local configuration.

## Performance and pilot

`node scripts/load-rehearsal.mjs` resets only `.runtime/load.sqlite`, creates 100 synthetic members and measures 200 HTTP dashboard requests from 20 clients on port 8124. It uses the PHP development server and one synthetic administrator session. Measured client timings include queueing/body transfer; they do not establish the proposed server p95<500ms or agreed-device two-second usability target. Repeat on the chosen host with representative roles, data volumes, devices and live integration latency.

The synthetic pilot test covers 12 lessons and completion. Real coordinator calibration, fairness/error review, useful learning outcomes, review workload, model spend and named support owners require the representative cohort in `PILOT_RELEASE_GATES.md`. No automatic pass mark can substitute for this sign-off.
