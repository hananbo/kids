<?php

namespace App\Games\HebrewQuiz;

use App\Models\QuizRun;
use Illuminate\Support\Facades\DB;

class AdvanceQuizRun
{
    public function __invoke(QuizRun $run, int $questionId): void
    {
        DB::transaction(function () use ($run, $questionId): void {
            $run = QuizRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($run->completed_at) {
                return;
            }

            $question = $run->questions()->where('position', $run->position)->firstOrFail();

            if ($question->id !== $questionId || ! $question->answered_at) {
                return;
            }

            $nextPosition = $run->position + 1;

            if ($run->questions()->where('position', $nextPosition)->exists()) {
                $run->update(['position' => $nextPosition]);

                return;
            }

            $run->update(['completed_at' => now()]);
        });
    }
}
