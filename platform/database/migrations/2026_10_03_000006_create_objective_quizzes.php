<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objective_quizzes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('learning_block_id')->constrained();
            $t->unsignedInteger('lesson_index');
            $t->json('questions');
            $t->json('answer_key');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->text('reason');
            $t->timestamps();
            $t->unique(['learning_block_id', 'lesson_index']);
        });
        Schema::table('work_drafts', fn (Blueprint $t) => $t->json('answers')->nullable());
        Schema::table('submission_attempts', function (Blueprint $t) {
            $t->json('answers')->nullable();
            $t->json('quiz_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('submission_attempts', fn (Blueprint $t) => $t->dropColumn(['answers', 'quiz_snapshot']));
        Schema::table('work_drafts', fn (Blueprint $t) => $t->dropColumn('answers'));
        Schema::dropIfExists('objective_quizzes');
    }
};
