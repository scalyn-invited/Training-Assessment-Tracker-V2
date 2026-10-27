<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_brandings', function (Blueprint $table) {
            $table->json('theme_colours')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_brandings', fn (Blueprint $table) => $table->dropColumn('theme_colours'));
    }
};
