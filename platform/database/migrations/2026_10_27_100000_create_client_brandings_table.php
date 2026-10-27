<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_brandings', function (Blueprint $table) {
            $table->uuid('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->string('environment', 32);
            $table->string('mode', 20);
            $table->string('client_name', 120);
            $table->string('app_name', 120);
            $table->string('accent', 7);
            $table->string('logo_path')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->primary(['organisation_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_brandings');
    }
};
