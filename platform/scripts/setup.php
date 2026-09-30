<?php

use App\Services\MockIdentity;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// Local synthetic setup only. Does not overwrite .env, rotate an existing key, or reset data.
$root = dirname(__DIR__);
if (! file_exists($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Run composer install first.\n");
    exit(1);
}
if (! file_exists($root.'/.env')) {
    copy($root.'/.env.example', $root.'/.env');
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
