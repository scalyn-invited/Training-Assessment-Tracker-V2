<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_blocks', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->foreignUuid('programme_version_id')->constrained();
            $t->foreignUuid('member_calendar_id')->constrained();
            $t->unsignedInteger('number');
            $t->unsignedInteger('version');
            $t->string('state')->default('current');
            $t->json('content');
            $t->json('rubric');
            $t->timestamp('started_at')->nullable();
            $t->boolean('early_start')->default(false);
            $t->foreignUuid('actor_id')->constrained('users');
            $t->text('reason');
            $t->timestamps();
            $t->unique(['enrolment_id', 'number', 'version']);
        });
        Schema::create('work_drafts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('learning_block_id')->constrained();
            $t->unsignedInteger('lesson_index');
            $t->foreignUuid('member_id')->constrained('users');
            $t->unsignedInteger('version');
            $t->text('body')->nullable();
            $t->json('files');
            $t->timestamps();
            $t->unique(['learning_block_id', 'lesson_index', 'member_id'], 'draft_lesson_member');
        });
        Schema::create('submission_attempts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->foreignUuid('learning_block_id')->constrained();
            $t->unsignedInteger('lesson_index');
            $t->foreignUuid('member_id')->constrained('users');
            $t->unsignedInteger('attempt');
            $t->unsignedInteger('version')->default(1);
            $t->string('status');
            $t->longText('body')->nullable();
            $t->json('files');
            $t->json('rubric');
            $t->string('content_hash', 64);
            $t->string('idempotency_key', 80);
            $t->unsignedInteger('draft_version');
            $t->uuid('approved_grade_id')->nullable();
            $t->timestamps();
            $t->unique(['member_id', 'idempotency_key']);
            $t->unique(['learning_block_id', 'lesson_index', 'attempt'], 'submission_lesson_attempt');
        });
        Schema::create('grade_attempts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('submission_attempt_id')->constrained();
            $t->unsignedInteger('version');
            $t->string('status');
            $t->json('result')->nullable();
            $t->decimal('total', 7, 3)->nullable();
            $t->string('rubric_hash', 64);
            $t->foreignUuid('actor_id')->constrained('users');
            $t->text('reason');
            $t->timestamps();
            $t->unique(['submission_attempt_id', 'version']);
        });
        Schema::create('grade_decisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('grade_attempt_id')->unique()->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->string('outcome');
            $t->text('reason');
            $t->json('scores');
            $t->decimal('total', 7, 3)->nullable();
            $t->timestamps();
        });
        Schema::create('appeals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('submission_attempt_id')->constrained();
            $t->foreignUuid('grade_decision_id')->constrained();
            $t->foreignUuid('reviewer_id')->nullable()->constrained('users');
            $t->foreignUuid('resolved_by')->nullable()->constrained('users');
            $t->string('status')->default('open');
            $t->text('reason');
            $t->text('resolution')->nullable();
            $t->timestamps();
            $t->unique('grade_decision_id');
        });
        Schema::create('learning_notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->foreignUuid('recipient_id')->constrained('users');
            $t->string('event_key')->unique();
            $t->string('kind');
            $t->string('status')->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('available_at')->nullable();
            $t->string('error_code')->nullable();
            $t->timestamps();
        });
        Schema::table('users', fn (Blueprint $t) => $t->boolean('training_email')->default(true));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('training_email'));
        foreach (['learning_notifications', 'appeals', 'grade_decisions', 'grade_attempts', 'submission_attempts', 'work_drafts', 'learning_blocks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
