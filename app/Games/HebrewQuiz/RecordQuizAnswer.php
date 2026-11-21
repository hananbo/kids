<?php

namespace App\Games\HebrewQuiz;

use App\Models\QuizRun;
use Illuminate\Support\Facades\DB;

class RecordQuizAnswer
{
    public const CHOICES = ['correct_answer', 'distractor_one', 'distractor_two', 'distractor_three'];

    public function __invoke(QuizRun $run, int $questionId, int $choice): void
    {
        DB::transaction(function () use ($run, $questionId, $choice): void {
            $run = QuizRun::query()->lockForUpdate()->findOrFail($run->id);

            if (! $run->isActive()) {
                return;
            }

            $question = $run->questions()->where('position', $run->position)->firstOrFail();

            if ($question->id !== $questionId || $question->answered_at) {
                return;
            }

            $question->update([
                'selected_choice' => $choice,
                'is_correct' => $question->choice_order[$choice] === 'correct_answer',
                'answered_at' => now(),
            ]);
        });
    }
}
