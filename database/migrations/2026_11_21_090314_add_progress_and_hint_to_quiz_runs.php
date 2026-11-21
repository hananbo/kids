<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_runs', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedTinyInteger('unlocked_level')->nullable();
            $table->unsignedTinyInteger('hint_position')->nullable();
        });

        foreach ([4 => 2, 7 => 3] as $difficulty => $level) {
            DB::table('quiz_runs')
                ->where('game', 'hebrew-quiz')
                ->whereIn('id', DB::table('quiz_run_questions')
                    ->join('published_question_versions', 'published_question_versions.id', '=', 'quiz_run_questions.published_question_version_id')
                    ->where('quiz_run_questions.position', 0)
                    ->where('published_question_versions.difficulty', $difficulty)
                    ->select('quiz_run_questions.quiz_run_id'))
                ->update(['level' => $level]);
        }
    }

    public function down(): void
    {
        Schema::table('quiz_runs', function (Blueprint $table) {
            $table->dropColumn(['level', 'unlocked_level', 'hint_position']);
        });
    }
};
