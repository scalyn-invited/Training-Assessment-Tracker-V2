<?php

use App\Models\User;
use App\Services\LocalAccounts;
use App\Services\Tenancy\PlatformAdministration;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$expected = str_replace('\\', '/', realpath(base_path('.runtime'))).'/m2-browser.sqlite';
if (str_replace('\\', '/', config('database.connections.sqlite.database')) !== $expected || ! app()->environment('local')) {
    throw new RuntimeException('Wrong browser database');
}
config(['training.authentication_mode' => 'mock', 'training.mock_identity' => true]);
Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
$admin = User::where('email', 'admin@example.invalid')->firstOrFail();
$platform = app(PlatformAdministration::class);
$platform->appoint(null, $admin->id, 'M2 browser bootstrap', 'm2-browser');
$platform->client($admin->fresh(), null, 'Browser second client', 0, 'Create browser invitation destination');
config(['training.authentication_mode' => 'local_password']);
$service = app(LocalAccounts::class);
$link = $service->bootstrap($admin->fresh(), 'm2-browser', 'Initial browser password');
$service->setPassword($link['token'], 'setup', 'Fifteen plus characters!', 'Fifteen plus characters!');
echo "Synthetic browser fixture ready.\n";
