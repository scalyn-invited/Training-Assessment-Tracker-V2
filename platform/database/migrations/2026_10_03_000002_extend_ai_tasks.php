<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_runs', fn (Blueprint $t) => $t->json('task_input')->nullable());
    }

    public function down(): void
    {
        Schema::table('ai_runs', fn (Blueprint $t) => $t->dropColumn('task_input'));
    }
};
