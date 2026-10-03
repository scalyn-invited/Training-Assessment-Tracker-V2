<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_holds', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enrolment_id')->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->text('reason');
            $t->timestamp('released_at')->nullable();
            $t->timestamps();
        });
        Schema::create('deletion_ledger', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('kind');
            $t->uuid('target_id');
            $t->string('object_hash', 64)->nullable();
            $t->string('policy_version');
            $t->timestamp('created_at');
            $t->unique(['kind', 'target_id']);
        });
        Schema::create('operational_heartbeats', function (Blueprint $t) {
            $t->string('component')->primary();
            $t->timestamp('seen_at');
        });
    }

    public function down(): void
    {
        foreach (['operational_heartbeats', 'deletion_ledger', 'legal_holds'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
