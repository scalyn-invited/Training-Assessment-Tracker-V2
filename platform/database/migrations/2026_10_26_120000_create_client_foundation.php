<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->unique(['environment', 'id'], 'users_environment_identity'));
        // Existing groups are deliberately unclassified until an explicit mapping is reviewed.
        Schema::table('groups', function (Blueprint $t) {
            $t->enum('kind', ['team', 'cohort'])->nullable();
            $t->unique(['organisation_id', 'environment', 'id'], 'groups_tenant_identity');
        });
        Schema::create('client_memberships', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment', 20);
            $t->foreignUuid('user_id')->constrained();
            $t->enum('status', ['active', 'ended'])->default('active');
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('joined_at');
            $t->timestamp('ended_at')->nullable();
            $t->timestamps();
            $t->unique(['organisation_id', 'environment', 'user_id'], 'client_person_unique');
            $t->unique(['organisation_id', 'environment', 'id'], 'client_membership_tenant');
            $t->unique(['organisation_id', 'environment', 'id', 'user_id'], 'client_membership_actor');
            $t->foreign(['environment', 'user_id'], 'cm_user_environment_fk')->references(['environment', 'id'])->on('users');
            $t->index(['user_id', 'environment', 'status'], 'client_membership_lookup');
        });
        // Side-by-side periods avoid changing the legacy memberships contract before cutover.
        Schema::create('client_team_memberships', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment', 20);
            $t->uuid('group_id');
            $t->uuid('client_membership_id');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->unsignedTinyInteger('active_slot')->nullable();
            $t->timestamps();
            $t->foreign(['organisation_id', 'environment', 'group_id'], 'ctm_group_fk')->references(['organisation_id', 'environment', 'id'])->on('groups');
            $t->foreign(['organisation_id', 'environment', 'client_membership_id'], 'ctm_member_fk')->references(['organisation_id', 'environment', 'id'])->on('client_memberships');
            $t->unique(['group_id', 'client_membership_id', 'active_slot'], 'ctm_active_unique');
            $t->index(['client_membership_id', 'starts_at', 'ends_at'], 'ctm_period_lookup');
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->enum('code', ['admin', 'coordinator', 'member'])->unique();
        });
        foreach (['admin', 'coordinator', 'member'] as $code) {
            DB::table('roles')->insert(['id' => (string) Str::uuid(), 'code' => $code]);
        }
        Schema::create('role_assignments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained();
            $t->foreignUuid('role_id')->constrained();
            $t->foreignUuid('organisation_id')->nullable()->constrained();
            $t->string('environment', 20);
            $t->uuid('client_membership_id')->nullable();
            $t->uuid('group_id')->nullable();
            $t->enum('scope_type', ['platform', 'client', 'team']);
            $t->string('scope_key', 80);
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->unsignedTinyInteger('active_slot')->nullable();
            $t->foreignUuid('granted_by')->constrained('users');
            $t->text('reason');
            $t->timestamps();
            $t->foreign(['organisation_id', 'environment', 'client_membership_id', 'user_id'], 'ra_member_fk')->references(['organisation_id', 'environment', 'id', 'user_id'])->on('client_memberships');
            $t->foreign(['organisation_id', 'environment', 'group_id'], 'ra_group_fk')->references(['organisation_id', 'environment', 'id'])->on('groups');
            $t->unique(['user_id', 'role_id', 'environment', 'scope_key', 'active_slot'], 'ra_active_unique');
            $t->index(['organisation_id', 'environment', 'group_id'], 'ra_scope_lookup');
        });
        Schema::create('coordinator_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment', 20);
            $t->uuid('member_membership_id');
            $t->uuid('coordinator_membership_id');
            $t->enum('kind', ['primary', 'backup']);
            $t->unsignedTinyInteger('primary_slot')->nullable();
            $t->unsignedTinyInteger('active_slot')->nullable();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->foreignUuid('assigned_by')->constrained('users');
            $t->text('reason');
            $t->timestamps();
            foreach (['member_membership_id' => 'ca_member_fk', 'coordinator_membership_id' => 'ca_coordinator_fk'] as $column => $name) {
                $t->foreign(['organisation_id', 'environment', $column], $name)->references(['organisation_id', 'environment', 'id'])->on('client_memberships');
            }
            $t->unique(['member_membership_id', 'primary_slot'], 'ca_primary_unique');
            $t->unique(['member_membership_id', 'coordinator_membership_id', 'active_slot'], 'ca_active_unique');
            $t->index(['coordinator_membership_id', 'ends_at'], 'ca_lookup');
        });
    }

    public function down(): void
    {
        // A populated foundation cannot be rolled back by discarding its access history.
        if (DB::table('groups')->whereNotNull('kind')->exists()) {
            throw new RuntimeException('Client foundation contains records; preserve explicit group classifications.');
        }
        foreach (['coordinator_allocations', 'role_assignments', 'client_team_memberships', 'client_memberships'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Client foundation contains records; restore/reconcile rather than deleting history.');
            }
        }
        foreach (['coordinator_allocations', 'role_assignments', 'roles', 'client_team_memberships', 'client_memberships'] as $table) {
            Schema::dropIfExists($table);
        }
        // InnoDB may replace its implicit FK index with our composite index on up().
        // Restore a legacy-compatible index before removing that composite index.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $hasLegacyIndex = collect(Schema::getIndexes('groups'))->contains(
                fn (array $index) => $index['name'] !== 'groups_tenant_identity'
                    && ($index['columns'][0] ?? null) === 'organisation_id'
            );
            if (! $hasLegacyIndex) {
                Schema::table('groups', fn (Blueprint $t) => $t->index('organisation_id', 'groups_organisation_id_foreign'));
            }
        }
        Schema::table('groups', function (Blueprint $t) {
            $t->dropUnique('groups_tenant_identity');
            $t->dropColumn('kind');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropUnique('users_environment_identity'));
    }
};
