<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quiz_run_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('published_question_version_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->json('choice_order');
            $table->unsignedTinyInteger('selected_choice')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->unique(['quiz_run_id', 'position']);
            $table->unique(['quiz_run_id', 'published_question_version_id'], 'quiz_run_unique_version');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_run_questions');
    }
};
