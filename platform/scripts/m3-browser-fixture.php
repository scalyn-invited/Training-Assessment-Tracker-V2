<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$expected = str_replace('\\', '/', base_path('.runtime/browser.sqlite'));
if (str_replace('\\', '/', config('database.connections.sqlite.database')) !== $expected || config('training.environment') !== 'test' || ! app()->environment('local')) {
    throw new RuntimeException('Only isolated browser fixtures may be refreshed.');
}
DB::table('users')->where('is_synthetic', true)->where('environment', 'test')->update(['permissions_synced_at' => now()]);
echo "Synthetic browser permission snapshot refreshed.\n";
