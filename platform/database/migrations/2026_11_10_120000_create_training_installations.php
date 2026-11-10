<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The installer creates its ownership receipt before the first migration.
        if (! Schema::hasTable('training_installations')) {
            Schema::create('training_installations', function (Blueprint $table) {
                $table->string('installation_id', 64)->primary();
                $table->string('stage', 32);
                $table->string('fixture_version', 64)->nullable();
                $table->longText('fixture_state')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_installations');
    }
};
