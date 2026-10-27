<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientFoundationMigrationTest extends TestCase
{
    public function test_additive_upgrade_and_empty_rollback_preserve_legacy_rows(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.client_rehearsal' => ['driver' => 'sqlite', 'database' => ':memory:',
            'prefix' => '', 'foreign_key_constraints' => true], 'training.environment' => 'test', 'training.mock_identity' => true, 'training.legacy_fixture' => true]);
        DB::setDefaultConnection('client_rehearsal');
        Storage::fake('local');
        try {
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'client_rehearsal', '--force' => true]));
            $migration = require database_path('migrations/2026_10_26_120000_create_client_foundation.php');
            if (Schema::hasTable('client_sensitive_grants')) {
                (require database_path('migrations/2026_10_26_170000_create_client_sensitive_grants.php'))->down();
            }
            $migration->down();
            $this->seed();
            $tables = ['users', 'organisations', 'groups', 'memberships', 'coordinator_assignments', 'enrolments', 'evidence_files'];
            $before = [];
            foreach ($tables as $table) {
                $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            }
            $migration->up();
            foreach ($tables as $table) {
                $after = DB::table($table)->orderBy('id')->get()->map(function ($row) use ($table) {
                    $value = (array) $row;
                    if ($table === 'groups') {
                        $this->assertNull($value['kind'], 'Existing groups must not be guessed into a type.');
                        unset($value['kind']);
                    }

                    return $value;
                })->all();
                $this->assertSame($before[$table], $after, $table.' changed during upgrade');
            }
            $this->assertSame(0, DB::table('client_memberships')->count());
            $this->assertSame(0, DB::table('role_assignments')->count());
            $this->assertSame(3, DB::table('roles')->count());
            $migration->down();
            foreach ($tables as $table) {
                $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
            }
            $migration->up();
            $this->assertSame(3, DB::table('roles')->count());
            (require database_path('migrations/2026_10_26_170000_create_client_sensitive_grants.php'))->up();
            foreach ($tables as $table) {
                $after = DB::table($table)->orderBy('id')->get()->map(function ($row) {
                    $data = (array) $row;
                    unset($data['kind']);

                    return $data;
                })->all();
                $this->assertSame($before[$table], $after, 'Sensitive migration changed '.$table);
            }
        } finally {
            DB::purge('client_rehearsal');
            DB::setDefaultConnection($original);
        }
    }
}
