<?php

use App\Jobs\DeliverOutbox;
use App\Models\Enrolment;
use App\Models\EvidenceFile;
use App\Services\Access;
use App\Services\Operations;
use App\Services\Outbox;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Isolated SQLite rehearsal only. Never accepts an existing database or production path.
require __DIR__.'/../vendor/autoload.php';
$root = realpath(__DIR__.'/..');
$run = $root.'/.runtime/restore-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
mkdir($run, 0700, true);
mkdir($run.'/source-files', 0700);
mkdir($run.'/restored-files', 0700);
$started = microtime(true);
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});
$app->detectEnvironment(fn () => 'testing');
$source = $run.'/source.sqlite';
touch($source);
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $source, 'database.connections.sqlite.url' => null,
    'filesystems.disks.local.root' => $run.'/source-files', 'training.environment' => 'test', 'training.mock_identity' => true, 'training.smtp_enabled' => false,
    'ai.live_enabled' => false, 'queue.default' => 'database', 'cache.default' => 'array', 'mail.default' => 'array', 'training.retention_approved' => true]);
DB::purge();
if (Artisan::call('migrate', ['--force' => true]) !== 0 || Artisan::call('db:seed', ['--force' => true]) !== 0) {
    throw new RuntimeException('Fixture creation failed');
}
$db = DB::connection();
$enrolment = Enrolment::firstOrFail();
$member = $enrolment->member;
$event = app(Outbox::class)->requestCheck($member, $enrolment, $enrolment->version, (string) Str::uuid());
$counts = [];
foreach (['users', 'enrolments', 'identities', 'evidence_files', 'outbox_events'] as $table) {
    $counts[$table] = $db->table($table)->count();
}
$snapshot = $run.'/snapshot.sqlite';
$db->statement('VACUUM INTO '.$db->getPdo()->quote($snapshot));
$files = [];
foreach (Storage::disk('local')->allFiles() as $key) {
    $files[$key] = base64_encode(Storage::disk('local')->get($key));
}
$payload = json_encode(['database' => base64_encode(file_get_contents($snapshot)), 'files' => $files, 'application_key' => 'synthetic-drill-key-only', 'idp' => ['kind' => 'mock-fixture', 'live_restore_verified' => false]], JSON_THROW_ON_ERROR);
$key = random_bytes(32);
$nonce = random_bytes(12);
$tag = '';
$cipher = openssl_encrypt($payload, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'training-restore-drill-v1');
if ($cipher === false) {
    throw new RuntimeException('Backup encryption failed');
}
file_put_contents($run.'/backup.enc', $nonce.$tag.$cipher);
file_put_contents($run.'/separate-test-key.bin', $key);
$old = EvidenceFile::firstOrFail();
$old->update(['created_at' => now()->subMonths(25)]);
app(Operations::class)->retain(true);
$ledger = DB::table('deletion_ledger')->get()->map(fn ($row) => (array) $row)->all();
$decoded = openssl_decrypt(substr(file_get_contents($run.'/backup.enc'), 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'training-restore-drill-v1');
if ($decoded === false) {
    throw new RuntimeException('Restore authentication failed');
}
$restored = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
$target = $run.'/restored.sqlite';
file_put_contents($target, base64_decode($restored['database'], true));
config(['database.connections.sqlite.database' => $target, 'filesystems.disks.local.root' => $run.'/restored-files']);
DB::purge('sqlite');
Storage::forgetDisk('local');
foreach ($restored['files'] as $name => $data) {
    if (str_contains($name, '..') || str_starts_with($name, '/')) {
        throw new RuntimeException('Unsafe archive path');
    } Storage::disk('local')->put($name, base64_decode($data, true));
}
foreach ($counts as $table => $expected) {
    if (DB::table($table)->count() !== $expected) {
        throw new RuntimeException('Restored row count mismatch: '.$table);
    }
}
foreach (EvidenceFile::all() as $file) {
    if (! hash_equals($file->sha256, hash('sha256', Storage::disk('local')->get($file->storage_key)))) {
        throw new RuntimeException('Restored file hash mismatch');
    }
}
if (DB::select('PRAGMA integrity_check')[0]->integrity_check !== 'ok') {
    throw new RuntimeException('Database integrity failure');
}
foreach ($ledger as $entry) {
    DB::table('deletion_ledger')->insertOrIgnore($entry);
}
$removed = app(Operations::class)->reapplyDeletions();
if ($removed !== 1 || Storage::disk('local')->exists($old->storage_key)) {
    throw new RuntimeException('Deletion ledger replay failed');
}
$job = new DeliverOutbox($event->id);
$job->handle(app(Access::class));
$job->handle(app(Access::class));
if (DB::table('notification_receipts')->where('outbox_event_id', $event->id)->count() !== 1) {
    throw new RuntimeException('Outbox replay not deduplicated');
}
$corrupt = $tag;
$corrupt[0] = chr(ord($corrupt[0]) ^ 1);
if (openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $corrupt, 'training-restore-drill-v1') !== false) {
    throw new RuntimeException('Corrupt archive accepted');
}
$report = ['kind' => 'isolated SQLite synthetic restore', 'passed' => true, 'counts' => $counts, 'private_file_hashes' => 'matched', 'authenticated_encryption' => 'round trip and tamper rejection passed',
    'deletion_ledger' => 'post-backup deletion reapplied', 'outbox' => 'duplicate replay produced one sink receipt', 'seconds' => round(microtime(true) - $started, 3),
    'limitations' => ['Not a MariaDB server restore', 'Uses a synthetic application key and mock IdP fixture', 'No production key, IdP, offsite storage, RPO or RTO qualification']];
file_put_contents($run.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
