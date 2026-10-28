<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_definitions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->uuid('family_id');
            $t->unsignedInteger('revision');
            $t->unsignedInteger('version')->default(1);
            $t->string('status')->default('draft');
            $t->string('title', 180);
            $t->json('questions');
            $t->json('rubric');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['family_id', 'revision']);
        });
        Schema::create('assessment_assignments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('definition_id')->constrained('assessment_definitions');
            $t->foreignUuid('enrolment_id')->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->unsignedInteger('version')->default(1);
            $t->string('status')->default('assigned');
            $t->longText('draft')->nullable();
            $t->uuid('approved_review_id')->nullable();
            $t->timestamps();
            $t->unique(['definition_id', 'enrolment_id']);
        });
        Schema::create('assessment_attempts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('assignment_id')->constrained('assessment_assignments');
            $t->unsignedInteger('number');
            $t->unsignedInteger('draft_version');
            $t->uuid('idempotency_key')->unique();
            $t->longText('snapshot');
            $t->string('content_hash', 64);
            $t->timestamps();
            $t->unique(['assignment_id', 'number']);
        });
        Schema::create('assessment_reviews', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('attempt_id')->constrained('assessment_attempts');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->unsignedInteger('sequence');
            $t->unique(['attempt_id', 'sequence']);
            $t->string('kind');
            $t->string('status');
            $t->longText('result')->nullable();
            $t->uuid('source_review_id')->nullable();
            $t->text('reason');
            $t->timestamps();
        });
        Schema::create('assessment_file_confirmations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('attempt_id')->constrained('assessment_attempts');
            $t->foreignUuid('evidence_file_id')->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->string('source_hash', 64);
            $t->unsignedInteger('version');
            $t->longText('text')->nullable();
            $t->text('reason');
            $t->timestamps();
        });
        Schema::create('assessment_appeals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('assignment_id')->constrained('assessment_assignments');
            $t->foreignUuid('review_id')->constrained('assessment_reviews');
            $t->foreignUuid('reviewer_id')->nullable()->constrained('users');
            $t->foreignUuid('role_assignment_id')->nullable()->constrained('role_assignments');
            $t->string('status')->default('open');
            $t->text('reason');
            $t->text('resolution')->nullable();
            $t->timestamps();
        });
        Schema::table('document_extractions', fn (Blueprint $t) => $t->foreignUuid('assessment_attempt_id')->nullable()->constrained('assessment_attempts'));
        Schema::table('ai_runs', fn (Blueprint $t) => $t->uuid('programme_version_id')->nullable()->change());
    }

    public function down(): void
    {
        // Initial-assessment runs have no programme. A populated rollback requires backup restoration.
        if (DB::table('ai_runs')->whereNull('programme_version_id')->exists()) {
            throw new RuntimeException('Restore the pre-M3 backup; assessment AI history must not be deleted by rollback.');
        }
        Schema::table('ai_runs', fn (Blueprint $t) => $t->uuid('programme_version_id')->nullable(false)->change());
        Schema::table('document_extractions', fn (Blueprint $t) => $t->dropConstrainedForeignId('assessment_attempt_id'));
        foreach (['assessment_appeals', 'assessment_file_confirmations', 'assessment_reviews', 'assessment_attempts', 'assessment_assignments', 'assessment_definitions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
