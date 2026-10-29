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
Illuminate\Support\Facades\DB::table('organisations')->where('id',$admin->organisation_id)->update(['name'=>'Z Current client']);
$other=$service->client($admin,null,'A Other client',0,'Synthetic other client');
for($i=0;$i<20;$i++) $service->client($admin,null,'B Pagination '.$i,0,'Synthetic pagination client');
config(['training.authentication_mode'=>'mock']);
$multi=App\Models\User::where('email','coordinator-a@example.invalid')->firstOrFail();
$service->clientRole($admin,$other,$multi->id,'join',$multi->permission_version,'Synthetic multiple membership');
config(['training.authentication_mode'=>'local_password']);
$accounts=app(App\Services\LocalAccounts::class);
$link=$accounts->bootstrap($admin->fresh(),'selection-test','Synthetic browser password');
$accounts->setPassword($link['token'],'setup','Selection test password!','Selection test password!');
$accounts->create($admin->fresh(),$admin->organisation_id,'Pending account','pending@example.invalid','Selection test password!','Synthetic pending account');
echo "Isolated selection fixture ready\n";
