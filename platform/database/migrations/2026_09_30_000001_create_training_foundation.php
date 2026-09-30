<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignUuid('organisation_id')->nullable()->constrained();
            $t->string('environment')->default('test')->index();
            $t->string('role')->default('member');
            $t->boolean('active')->default(true);
            $t->boolean('is_synthetic')->default(true);
            $t->boolean('sensitive_access')->default(false);
            $t->string('origin')->default('manual');
            $t->string('sync_policy')->default('local_only');
            $t->string('external_person_id')->nullable();
            $t->timestamp('permissions_synced_at')->nullable();
            $t->unsignedInteger('directory_version')->default(0);
            $t->unsignedInteger('permission_version')->default(1);
            $t->unique(['organisation_id', 'environment', 'external_person_id'], 'users_external_person_unique');
        });
        Schema::create('identities', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained();
            $t->string('issuer', 160);
            $t->string('subject', 160);
            $t->boolean('active')->default(true);
            $t->unique(['issuer', 'subject']);
            $t->timestamps();
        });
        Schema::create('groups', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->string('name');
            $t->string('origin')->default('manual');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        foreach (['memberships', 'coordinator_assignments'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('organisation_id')->constrained();
                $t->string('environment');
                $t->foreignUuid('group_id')->constrained();
                $t->foreignUuid('user_id')->constrained();
                $t->timestamp('starts_at');
                $t->timestamp('ends_at')->nullable();
                $t->timestamps();
                $t->unique(['group_id', 'user_id']);
            });
        }
        Schema::create('enrolments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->foreignUuid('member_id')->constrained('users');
            $t->foreignUuid('group_id')->constrained();
            $t->foreignUuid('coordinator_id')->constrained('users');
            $t->string('title');
            $t->string('status')->default('onboarding');
            $t->unsignedInteger('version')->default(1);
            $t->string('timezone')->default('Asia/Manila');
            $t->unsignedSmallInteger('duration_weeks')->default(4);
            $t->unsignedSmallInteger('daily_minutes')->default(30);
            $t->timestamps();
            $t->index(['organisation_id', 'environment', 'member_id']);
        });
        Schema::create('evidence_files', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->foreignUuid('enrolment_id')->constrained();
            $t->string('storage_key')->unique();
            $t->string('original_name');
            $t->string('mime');
            $t->unsignedBigInteger('bytes');
            $t->string('sha256', 64);
            $t->string('scan_status')->default('quarantined');
            $t->boolean('sensitive')->default(false);
            $t->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->foreignUuid('actor_id')->nullable()->constrained('users');
            $t->string('action');
            $t->string('target_id');
            $t->uuid('request_id');
            $t->json('metadata');
            $t->timestamp('created_at');
        });
        Schema::create('outbox_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->foreignUuid('enrolment_id')->constrained();
            $t->unsignedInteger('enrolment_version');
            $t->string('idempotency_key', 128);
            $t->string('payload_hash', 64);
            $t->string('type');
            $t->string('status')->default('pending')->index();
            $t->unsignedInteger('attempts')->default(0);
            $t->string('error_code')->nullable();
            $t->timestamps();
            $t->unique(['actor_id', 'idempotency_key']);
        });
        Schema::create('notification_receipts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('outbox_event_id')->unique()->constrained();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->foreignUuid('recipient_id')->constrained('users');
            $t->string('transport')->default('database-sink');
            $t->string('subject');
            $t->timestamp('created_at');
        });
        Schema::create('inbound_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organisation_id')->constrained();
            $t->string('environment');
            $t->uuid('event_id')->unique();
            $t->string('payload_hash', 64);
            $t->string('entity_id');
            $t->unsignedInteger('entity_version');
            $t->string('outcome');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['inbound_events', 'notification_receipts', 'outbox_events', 'audit_events', 'evidence_files', 'enrolments', 'coordinator_assignments', 'memberships', 'groups', 'identities'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['organisation_id']);
            $t->dropUnique('users_external_person_unique');
            $t->dropColumn(['organisation_id', 'environment', 'role', 'active', 'is_synthetic', 'sensitive_access', 'origin', 'sync_policy', 'external_person_id', 'permissions_synced_at', 'directory_version', 'permission_version']);
        });
        Schema::dropIfExists('organisations');
    }
};
