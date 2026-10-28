<?php

use App\Services\LocalAccounts;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = $argv[1] ?? '';
if (! is_file($database) || ! str_starts_with(basename($database), 'tat')
    || realpath(dirname($database)) !== realpath(sys_get_temp_dir())) {
    exit(2);
}
DB::purge('sqlite');
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database,
    'training.localhost_database' => $database, 'training.localhost_production' => true,
    'training.environment' => 'production', 'training.authentication_mode' => 'local_password']);
$app->instance('env', 'production');
echo "READY\n";
fflush(STDOUT);
fgets(STDIN);
try {
    app(LocalAccounts::class)->initializeProduction('Operator', 'operator@example.org', 'race-worker', 'Concurrent bootstrap rehearsal');
    echo "OK\n";
} catch (Throwable $error) {
    echo get_class($error)."\n";
    exit(1);
}
