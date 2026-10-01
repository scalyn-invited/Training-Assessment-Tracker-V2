<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained();
            $table->string('environment');
            $table->string('task');
            $table->unsignedInteger('version');
            $table->json('routing');
            $table->foreignUuid('actor_id')->constrained('users');
            $table->timestamps();
            $table->unique(['organisation_id', 'environment', 'task', 'version'], 'ai_policy_version');
        });
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained();
            $table->string('environment');
            $table->foreignUuid('enrolment_id')->constrained();
            $table->foreignUuid('actor_id')->constrained('users');
            $table->foreignUuid('programme_version_id')->constrained();
            $table->foreignUuid('result_version_id')->nullable()->constrained('programme_versions');
            $table->string('idempotency_key', 80);
            $table->string('payload_hash', 64);
            $table->string('status')->index();
            $table->json('configuration');
            $table->text('feedback')->nullable();
            $table->unsignedBigInteger('reserved');
            $table->unsignedBigInteger('spent')->default(0);
            $table->string('message')->nullable();
            $table->timestamps();
            $table->unique(['actor_id', 'idempotency_key']);
        });
        Schema::create('ai_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_run_id')->constrained();
            $table->unsignedInteger('block_index');
            $table->string('status')->default('queued')->index();
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedInteger('repairs')->default(0);
            $table->unsignedInteger('provider_index')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->json('output')->nullable();
            $table->timestamps();
            $table->unique(['ai_run_id', 'block_index']);
        });
        Schema::create('ai_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_block_id')->constrained();
            $table->unsignedInteger('number');
            $table->string('provider');
            $table->string('model');
            $table->string('status');
            $table->string('external_id')->nullable();
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('cost')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
            $table->unique(['ai_block_id', 'number']);
        });
    }

    public function down(): void
    {
        foreach (['ai_attempts', 'ai_blocks', 'ai_runs', 'ai_policies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
