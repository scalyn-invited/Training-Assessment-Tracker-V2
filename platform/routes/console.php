<?php

use App\Jobs\DeliverOutbox;
use App\Models\Organisation;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\MockIdentity;
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

Artisan::command('training:directory-import {file} {organisation}', function () {
    $event = json_decode(file_get_contents($this->argument('file')), true, 512, JSON_THROW_ON_ERROR);
    $result = app(SandboxDirectory::class)->apply(Organisation::findOrFail($this->argument('organisation')), $event);
    $this->info($result);
})->purpose('Apply a synthetic directory event through the local contract adapter');

Artisan::command('training:mock-token {email}', function () {
    $this->line(app(MockIdentity::class)->issue(User::where('email', $this->argument('email'))->firstOrFail()));
})->purpose('Issue a five-minute local-only mock delegated credential');
