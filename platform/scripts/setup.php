<?php

use App\Services\MockIdentity;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// Explicit legacy demo only. The default installation is the browser wizard.
$root = dirname(__DIR__);
if (! in_array('--demo', $argv, true)) {
    echo "Create an empty MariaDB database in phpMyAdmin. Run php artisan serve and open http://127.0.0.1:8000/setup.\n";
    echo "Existing SQLite installs: stop workers/server, then run php artisan training:prepare-local-upgrade.\n";
    exit(0);
}
if (! file_exists($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Run composer install first.\n");
    exit(1);
}
if (! file_exists($root.'/.env')) {
    fwrite(STDERR, "Legacy demo requires an explicitly configured local/test mock .env and isolated SQLite database. See README.\n");
    exit(1);
}
if (! file_exists($root.'/database/database.sqlite')) {
    touch($root.'/database/database.sqlite');
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app(MockIdentity::class)->enabled()) {
    fwrite(STDERR, "Setup requires local/testing, TRAINING_ENVIRONMENT=test and MOCK_IDENTITY_ENABLED=true.\n");
    exit(1);
}
if (! config('app.key')) {
    Artisan::call('key:generate', ['--no-interaction' => true]);
}
foreach (['migrate', 'db:seed'] as $command) {
    $code = Artisan::call($command, ['--no-interaction' => true]);
    echo Artisan::output();
    if ($code !== 0) {
        exit($code);
    }
}
echo "Synthetic workspace ready. Run php artisan serve --host=127.0.0.1\n";
