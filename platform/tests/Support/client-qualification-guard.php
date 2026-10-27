<?php

use Illuminate\Support\Facades\DB;

function assertClientQualificationDatabase(): void
{
    if (! app()->environment('testing') || config('database.default') !== 'mariadb'
        || config('database.connections.mariadb.database') !== 'training_foundation_test'
        || config('training.environment') !== 'test') {
        throw new RuntimeException('Refusing database outside isolated synthetic MariaDB qualification.');
    }
    $server = DB::selectOne('SELECT DATABASE() AS db, @@datadir AS directory, VERSION() AS version');
    if ($server->db !== 'training_foundation_test') {
        throw new RuntimeException('Unexpected connected database.');
    }
    $expected = getenv('M1_EXPECTED_DATADIR');
    if ($expected) {
        $normalise = fn ($path) => strtolower(rtrim(str_replace('\\', '/', $path), '/'));
        if ($normalise($server->directory) !== $normalise($expected)) {
            throw new RuntimeException('Wrong MariaDB data directory; no fixture mutation allowed.');
        }
    } elseif (getenv('CI') !== 'true') {
        throw new RuntimeException('Local qualification requires M1_EXPECTED_DATADIR.');
    }
    if (! str_starts_with($server->version, '11.4.13-MariaDB')) {
        throw new RuntimeException('Qualification requires the CI MariaDB version 11.4.13.');
    }
}
