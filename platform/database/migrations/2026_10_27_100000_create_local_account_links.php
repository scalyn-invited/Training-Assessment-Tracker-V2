<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('local_password_enabled_at')->nullable();
        });
        Schema::create('local_account_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token_hash', 64)->unique();
            $table->string('purpose', 16);
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('issuer_id')->constrained('users');
            $table->unsignedInteger('issuer_version');
            $table->foreignUuid('organisation_id')->nullable()->constrained('organisations');
            $table->string('environment', 32);
            $table->boolean('bootstrap')->default(false);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_account_links');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('local_password_enabled_at'));
    }
};
