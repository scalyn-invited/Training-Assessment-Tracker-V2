<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_extractions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('evidence_file_id')->constrained('evidence_files');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->unsignedInteger('attempt');
            $t->unsignedInteger('version')->default(1);
            $t->string('status')->index();
            $t->string('source_hash', 64);
            $t->string('engine')->nullable();
            $t->unsignedInteger('page_count')->nullable();
            $t->longText('extracted_text')->nullable();
            $t->longText('review_data')->nullable();
            $t->json('warnings')->nullable();
            $t->string('error_code')->nullable();
            $t->uuid('lease')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->foreignUuid('confirmed_onboarding_id')->nullable()->constrained('onboarding_versions');
            $t->timestamps();
            $t->unique(['evidence_file_id', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extractions');
    }
};
