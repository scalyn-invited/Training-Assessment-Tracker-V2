<?php

use App\Models\Enrolment;
use App\Models\Identity;
use App\Models\User;
use App\Services\MockIdentity;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$root = realpath(__DIR__.'/..');
$expected = str_replace('\\', '/', $root.'/.runtime/load.sqlite');
if (str_replace('\\', '/', getenv('DB_DATABASE') ?: '') !== $expected || getenv('DB_CONNECTION') !== 'sqlite') {
    fwrite(STDERR, 'Only the isolated load fixture is permitted.');
    exit(1);
}
if (! is_dir($root.'/.runtime')) {
    mkdir($root.'/.runtime', 0700, true);
}if (! file_exists($expected)) {
    touch($expected);
}
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
});
app(MockIdentity::class)->assertEnabled();
config(['filesystems.disks.local.root' => $root.'/.runtime/load-files', 'hashing.bcrypt.rounds' => 4]);
if (Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]) !== 0) {
    throw new RuntimeException('Load fixture migration failed');
}
$base = Enrolment::firstOrFail();
for ($index = 0; $index < 98; $index++) {
    $user = User::create(['organisation_id' => $base->organisation_id, 'environment' => 'test', 'name' => 'Synthetic load learner '.$index, 'email' => 'load-'.$index.'@example.invalid',
        'password' => Str::random(32), 'active' => true, 'is_synthetic' => true, 'origin' => 'manual', 'sync_policy' => 'local_only', 'role' => 'member']);
    Identity::create(['user_id' => $user->id, 'issuer' => config('training.mock_issuer'), 'subject' => 'load-'.$index]);
    DB::table('memberships')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $base->organisation_id, 'environment' => 'test', 'user_id' => $user->id, 'group_id' => $base->group_id, 'starts_at' => now()]);
    Enrolment::create(['organisation_id' => $base->organisation_id, 'environment' => 'test', 'member_id' => $user->id, 'coordinator_id' => $base->coordinator_id, 'group_id' => $base->group_id, 'title' => 'Synthetic load programme '.$index]);
}
echo 'Created 100 synthetic members with isolated enrolments.'.PHP_EOL;
