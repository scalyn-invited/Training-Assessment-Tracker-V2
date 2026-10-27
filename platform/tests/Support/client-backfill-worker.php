<?php

use App\Models\User;
use App\Services\Tenancy\ClientAdministration;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/../../scripts/support/ClientBackfill.php';
ClientBackfill::guard('training_foundation_test');
$manifest = json_decode(file_get_contents($argv[2]), true, flags: JSON_THROW_ON_ERROR);
echo "READY\n";
fflush(STDOUT);
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(4);
}
try {
    if ($argv[1] === 'apply') {
        $result = ClientBackfill::run($manifest, ClientBackfill::hash($manifest), $manifest['source_sha256'], 'training_foundation_test', true);
        echo $result['status'].PHP_EOL;
    } elseif ($argv[1] === 'revoke') {
        DB::transaction(function () use ($argv) {
            echo "TRY_LOCK\n";
            fflush(STDOUT);
            DB::table('organisations')->where('id', $argv[3])->lockForUpdate()->first();
            $grant = DB::table('role_assignments')->where('user_id', $argv[5])->first();
            if (! $grant) {
                throw new RuntimeException('Mapped grant absent after lock.');
            }
            app(ClientAdministration::class)->revokeRole(User::findOrFail($argv[4]), $argv[3], $grant->id, 'Concurrent post-backfill revocation');
        });
        echo "REVOKED\n";
    } else {
        throw new RuntimeException('Unknown operation.');
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    exit(2);
}
