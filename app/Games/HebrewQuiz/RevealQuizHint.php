<?php

namespace App\Games\HebrewQuiz;

use App\Models\QuizRun;
use Illuminate\Support\Facades\DB;

class RevealQuizHint
{
    public function __invoke(QuizRun $run, int $questionId): void
    {
        DB::transaction(function () use ($run, $questionId): void {
            $run = QuizRun::query()->lockForUpdate()->findOrFail($run->id);
            $question = $run->questions()->with('version')->where('position', $run->position)->find($questionId);

            if ($question && $run->canRevealHintFor($question)) {
                $run->update(['hint_position' => $run->position]);
            }
        });
    }
}
