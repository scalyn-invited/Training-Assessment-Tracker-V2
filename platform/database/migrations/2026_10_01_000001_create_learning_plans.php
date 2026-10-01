<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->unsignedInteger('version');
            $t->string('last_step');
            $t->json('data');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->timestamps();
            $t->unique(['enrolment_id', 'version']);
        });
        Schema::create('member_calendars', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('member_id')->constrained('users');
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->unsignedInteger('version');
            $t->string('timezone');
            $t->unsignedSmallInteger('daily_minutes');
            $t->json('weekdays');
            $t->json('holidays');
            $t->json('absences');
            $t->text('reason');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->timestamps();
            $t->unique(['member_id', 'version']);
        });
        Schema::create('programme_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->foreignUuid('onboarding_version_id')->constrained();
            $t->foreignUuid('member_calendar_id')->constrained();
            $t->unsignedInteger('version');
            $t->string('state')->default('draft');
            $t->json('content');
            $t->text('reason');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->timestamps();
            $t->unique(['enrolment_id', 'version']);
        });
        Schema::create('programme_approvals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('programme_version_id')->unique()->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->text('reason');
            $t->string('content_hash', 64);
            $t->timestamps();
        });
        Schema::create('capacity_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('programme_version_id')->constrained();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->foreignUuid('member_id')->constrained('users');
            $t->date('day');
            $t->unsignedSmallInteger('minutes');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['programme_version_id', 'day']);
            $t->index(['member_id', 'active', 'day']);
        });
    }

    public function down(): void
    {
        foreach (['capacity_allocations', 'programme_approvals', 'programme_versions', 'member_calendars', 'onboarding_versions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
