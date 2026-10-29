<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.connections.sqlite.database') !== str_replace('\\','/', __DIR__).'/test.sqlite' || config('training.environment') !== 'test') { throw new RuntimeException('Isolated fixture only'); }
config(['training.authentication_mode'=>'mock']);
Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
$service = app(App\Services\Tenancy\PlatformAdministration::class);
$admin = App\Models\User::where('email','admin@example.invalid')->firstOrFail();
$service->appoint(null,$admin->id,'Synthetic browser bootstrap','selection-test');
$admin->refresh();
config(['training.authentication_mode'=>'local_password']);
$accounts=app(App\Services\LocalAccounts::class);
$link=$accounts->bootstrap($admin->fresh(),'platform-theme-test','Synthetic browser password');
$accounts->setPassword($link['token'],'setup','Theme test password!','Theme test password!');
echo "Isolated theme fixture ready\n";
