<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->string('idempotency_key', 80)->unique();
            $t->string('payload_hash', 64);
            $t->json('payload');
            $t->string('status');
            $t->string('external_person_id')->nullable();
            $t->text('reason');
            $t->boolean('export_history')->default(false);
            $t->timestamps();
        });
        Schema::create('approved_changes', function (Blueprint $t) {
            $t->bigIncrements('sequence');
            $t->uuid('event_id')->unique();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->string('entity_id');
            $t->unsignedInteger('entity_version');
            $t->string('kind');
            $t->json('facts');
            $t->timestamp('created_at');
            $t->unique(['entity_id', 'entity_version', 'kind']);
        });
        Schema::create('integration_cursors', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->string('stream');
            $t->string('cursor')->nullable();
            $t->timestamp('last_success_at')->nullable();
            $t->timestamps();
            $t->unique(['organisation_id', 'environment', 'stream']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('linked_at')->nullable();
            $t->boolean('export_training_history')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['linked_at', 'export_training_history']));
        foreach (['integration_cursors', 'approved_changes', 'promotions'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
