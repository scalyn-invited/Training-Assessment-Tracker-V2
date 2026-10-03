<?php

use App\Jobs\DeliverOutbox;
use App\Jobs\WorkerHeartbeat;
use App\Models\Organisation;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\Ai\Generation;
use App\Services\FileScanning;
use App\Services\LearningNotices;
use App\Services\MockIdentity;
use App\Services\Operations;
use App\Services\Promotions;
use App\Services\SandboxDirectory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('training:outbox', function () {
    $count = 0;
    OutboxEvent::where('status', 'pending')->orderBy('id')->chunkById(100, function ($events) use (&$count) {
        foreach ($events as $event) {
            DeliverOutbox::dispatch($event->id);
            $count++;
        }
    });
    $this->info("Dispatched {$count} pending event(s). Duplicate deliveries are safe.");
})->purpose('Recover committed outbox events, including dispatch gaps after a crash');
Schedule::command('training:outbox')->everyMinute()->withoutOverlapping();

Artisan::command('training:notices', function () {
    app(LearningNotices::class)->recover();
    $this->info('Pending learning notices dispatched; uncertain sends remain held.');
});
Schedule::command('training:notices')->everyMinute()->withoutOverlapping();
Artisan::command('training:promotions', function () {
    app(Promotions::class)->recover();
});
Schedule::command('training:promotions')->everyMinute()->withoutOverlapping();
Artisan::command('training:scan-recover', function () {
    app(FileScanning::class)->recover();
});
Schedule::command('training:scan-recover')->everyFiveMinutes()->withoutOverlapping();
Artisan::command('training:health', function () {
    $report = app(Operations::class)->report();
    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return count($report['stale_components']) || $report['failed_jobs'] ? 1 : 0;
})->purpose('Emit operational metadata without assessment bodies or secrets');
Artisan::command('training:heartbeat', function () {
    app(Operations::class)->heartbeat('scheduler');
    WorkerHeartbeat::dispatch();
});
Schedule::command('training:heartbeat')->everyMinute()->withoutOverlapping();
Artisan::command('training:retention {--apply}', function () {
    $this->line(json_encode(app(Operations::class)->retain((bool) $this->option('apply')), JSON_THROW_ON_ERROR));
})->purpose('Dry-run evidence retention; --apply also requires an approved configured policy');
Artisan::command('training:reapply-deletions', function () {
    $this->info('Private objects removed: '.app(Operations::class)->reapplyDeletions());
})->purpose('Reapply the durable deletion ledger before making a restored system available');

Artisan::command('training:ai-recover', function () {
    $count = app(Generation::class)->recover();
    $this->info("Dispatched {$count} queued AI blocks. Interrupted calls retain their reservations for reconciliation.");
})->purpose('Recover AI dispatch gaps without repeating uncertain provider calls');
Schedule::command('training:ai-recover')->everyMinute()->withoutOverlapping();

Artisan::command('training:ai-prune', function () {
    $count = app(Generation::class)->prune();
    $this->info("Purged temporary feedback/output from {$count} terminal runs older than 30 days. Programme versions and billing metadata retained.");
})->purpose('Remove temporary AI trace content while preserving training and accounting history');
Schedule::command('training:ai-prune')->daily()->withoutOverlapping();

Artisan::command('training:directory-import {file} {organisation}', function () {
    $event = json_decode(file_get_contents($this->argument('file')), true, 512, JSON_THROW_ON_ERROR);
    $result = app(SandboxDirectory::class)->apply(Organisation::findOrFail($this->argument('organisation')), $event);
    $this->info($result);
})->purpose('Apply a synthetic directory event through the local contract adapter');

Artisan::command('training:mock-token {email}', function () {
    $this->line(app(MockIdentity::class)->issue(User::where('email', $this->argument('email'))->firstOrFail()));
})->purpose('Issue a five-minute local-only mock delegated credential');
