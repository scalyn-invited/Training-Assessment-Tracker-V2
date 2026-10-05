<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capacity_allocations', function (Blueprint $t) {
            $t->index('programme_version_id', 'capacity_programme_fk');
            $t->dropUnique(['programme_version_id', 'day']);
            $t->unsignedInteger('revision')->default(1);
            $t->unique(['programme_version_id', 'day', 'revision'], 'capacity_schedule_revision');
        });
        Schema::create('kpi_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->unsignedInteger('number');
            $t->unsignedInteger('version');
            $t->json('definition');
            $t->timestamp('effective_at');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->text('reason');
            $t->timestamps();
            $t->unique(['enrolment_id', 'number', 'version']);
        });
        Schema::create('kpi_observations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('kpi_version_id')->constrained();
            $t->foreignUuid('grade_decision_id')->nullable()->constrained();
            $t->foreignUuid('submission_attempt_id')->nullable()->constrained();
            $t->decimal('value', 15, 5);
            $t->string('evidence_type');
            $t->json('evidence');
            $t->boolean('current')->default(true);
            $t->foreignUuid('actor_id')->constrained('users');
            $t->timestamps();
            $t->unique(['grade_decision_id', 'kpi_version_id']);
        });
        Schema::create('kpi_snapshots', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('kpi_version_id')->constrained();
            $t->json('source_ids');
            $t->decimal('value', 15, 5)->nullable();
            $t->string('status');
            $t->unsignedInteger('sample_count');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->timestamps();
        });
        Schema::create('adaptation_proposals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->foreignUuid('learning_block_id')->nullable()->constrained();
            $t->foreignUuid('member_calendar_id')->constrained();
            $t->unsignedInteger('evidence_block');
            $t->json('source_ids');
            $t->string('policy_version');
            $t->string('status');
            $t->json('replacement')->nullable();
            $t->text('reason');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->foreignUuid('approved_by')->nullable()->constrained('users');
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
        Schema::create('kpi_suggestions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->json('source_ids');
            $t->json('actions')->nullable();
            $t->string('status');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->timestamps();
        });
        Schema::create('calendar_changes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->unsignedInteger('enrolment_version');
            $t->json('preview');
            $t->string('status');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->text('reason');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('capacity_allocations', function (Blueprint $t) {
            $t->dropUnique('capacity_schedule_revision');
            $t->dropColumn('revision');
            $t->unique(['programme_version_id', 'day']);
            $t->dropIndex('capacity_programme_fk');
        });
        foreach (['calendar_changes', 'kpi_suggestions', 'adaptation_proposals', 'kpi_snapshots', 'kpi_observations', 'kpi_versions'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
