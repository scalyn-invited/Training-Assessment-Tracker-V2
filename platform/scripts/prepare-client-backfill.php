<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

$options = getopt('', ['source:', 'target:', 'output:']);
foreach (['source', 'target', 'output'] as $key) {
    if (! isset($options[$key]) || ! is_string($options[$key])) {
        fwrite(STDERR, "Required: --source --target --output (new manifest file)\n");
        exit(2);
    }
}
if (getenv('APP_ENV') !== 'testing' || getenv('TRAINING_ENVIRONMENT') !== 'test'
    || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== $options['target'] || getenv('DB_URL')) {
    fwrite(STDERR, "Preparation requires explicit isolated SQLite test configuration.\n");
    exit(2);
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/support/ClientBackfill.php';
try {
    ClientBackfill::guard($options['target']);
    $source = realpath($options['source']);
    $target = realpath($options['target']);
    if (! $source || $source === $target || file_exists($options['output'])
        || ! hash_equals(hash_file('sha256', $source), hash_file('sha256', $target))
        || Schema::hasTable('client_memberships')) {
        throw new RuntimeException('Require an untouched disposable source copy and new manifest destination.');
    }
    $at = now()->utc()->format('Y-m-d H:i:s');
    // Validate eligibility before even additive DDL, and preserve every legacy row.
    ClientBackfill::mapping($at);
    $before = ClientBackfill::snapshot();
    $migration = require database_path('migrations/2026_10_26_120000_create_client_foundation.php');
    $migration->up();
    if ($before !== ClientBackfill::snapshot()) {
        throw new RuntimeException('Additive schema changed legacy rows; discard disposable target.');
    }
    $manifest = ClientBackfill::draft(hash_file('sha256', $source), $at);
    ClientBackfill::prepare($target, $manifest);
    $file = fopen($options['output'], 'x');
    if (! $file) {
        throw new RuntimeException('Cannot create manifest exclusively.');
    }
    fwrite($file, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    fclose($file);
    echo json_encode(['status' => 'prepared', 'manifest_sha256' => ClientBackfill::hash($manifest),
        'source_sha256' => $manifest['source_sha256'], 'legacy_snapshot' => $before], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    exit(2);
}
