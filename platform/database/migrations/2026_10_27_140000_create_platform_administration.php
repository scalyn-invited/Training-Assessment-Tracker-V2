<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $t) {
            $t->string('environment', 20)->primary();
            $t->unsignedInteger('version')->default(0);
            $t->string('display_name', 120)->nullable();
            $t->string('app_name', 120)->nullable();
            $t->string('accent', 7)->nullable();
            $t->string('default_theme', 30)->nullable();
            $t->string('logo_path')->nullable();
            $t->timestamps();
        });
        Schema::create('platform_client_states', function (Blueprint $t) {
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment', 20);
            $t->boolean('suspended')->default(false);
            $t->unsignedInteger('version')->default(0);
            $t->timestamps();
            $t->primary(['organisation_id', 'environment']);
        });
        Schema::create('platform_audit_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('environment', 20)->index();
            $t->foreignUuid('actor_id')->nullable()->constrained('users');
            $t->string('operator_reference', 160)->nullable();
            $t->string('action', 100);
            $t->string('target_id', 80);
            $t->text('reason');
            $t->json('changes');
            $t->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        if (DB::table('platform_audit_events')->exists()) {
            throw new RuntimeException('Preserve platform administration history; restore an isolated snapshot instead.');
        }
        Schema::dropIfExists('platform_audit_events');
        Schema::dropIfExists('platform_client_states');
        Schema::dropIfExists('platform_settings');
    }
};
