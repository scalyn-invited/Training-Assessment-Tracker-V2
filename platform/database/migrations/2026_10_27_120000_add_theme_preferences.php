<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_brandings', fn (Blueprint $table) => $table->string('default_theme', 32)->nullable());
        Schema::create('client_theme_preferences', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->uuid('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->string('environment', 32);
            $table->string('theme', 32)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->primary(['user_id', 'organisation_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_theme_preferences');
        Schema::table('client_brandings', fn (Blueprint $table) => $table->dropColumn('default_theme'));
    }
};
