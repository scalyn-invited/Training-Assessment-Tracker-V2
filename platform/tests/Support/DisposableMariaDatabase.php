<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

trait DisposableMariaDatabase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        require_once __DIR__.'/client-qualification-guard.php';
        assertClientQualificationDatabase();
        $this->refreshTestDatabase();
        // Tests rebuild this guarded disposable database; populated permission history
        // must never be removed through the application's destructive down migration.
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }
}
