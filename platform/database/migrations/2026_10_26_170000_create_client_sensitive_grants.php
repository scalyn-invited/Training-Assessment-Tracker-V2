<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $t) {
            $t->uuid('group_id')->nullable()->change();
        });
        Schema::create('client_sensitive_grants', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment', 20);
            $t->foreignUuid('allocation_id')->constrained('coordinator_allocations');
            $t->foreignUuid('granted_by')->constrained('users');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->unsignedTinyInteger('active_slot')->nullable();
            $t->text('reason');
            $t->timestamps();
            $t->unique(['allocation_id', 'active_slot'], 'sensitive_active_unique');
        });
        Schema::table('promotions', function (Blueprint $t) {
            $t->foreignUuid('organisation_id')->nullable()->constrained();
            $t->string('environment', 20)->nullable();
        });
        Schema::table('appeals', function (Blueprint $t) {
            $t->foreignUuid('reviewer_role_assignment_id')->nullable()->constrained('role_assignments');
            $t->timestamp('authority_granted_at')->nullable();
            $t->timestamp('authority_ended_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('client_sensitive_grants')->exists() || DB::table('appeals')->whereNotNull('reviewer_role_assignment_id')->exists()
            || DB::table('enrolments')->whereNull('group_id')->exists()) {
            throw new RuntimeException('Preserve sensitive grant and appeal authority history; restore/reconcile instead.');
        }
        Schema::table('appeals', function (Blueprint $t) {
            $t->dropForeign(['reviewer_role_assignment_id']);
            $t->dropColumn(['reviewer_role_assignment_id', 'authority_granted_at', 'authority_ended_at']);
        });
        Schema::table('promotions', function (Blueprint $t) {
            $t->dropForeign(['organisation_id']);
            $t->dropColumn(['organisation_id', 'environment']);
        });
        Schema::dropIfExists('client_sensitive_grants');
        Schema::table('enrolments', function (Blueprint $t) {
            $t->uuid('group_id')->nullable(false)->change();
        });
    }
};
