<?php

namespace App\Games\HebrewQuiz;

use App\Models\QuizRun;
use Illuminate\Support\Facades\DB;

class WalkAwayFromQuiz
{
    public function __invoke(QuizRun $run, int $questionId): void
    {
        DB::transaction(function () use ($run, $questionId): void {
            $run = QuizRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->isActive() && $run->questions()->where('position', $run->position)->whereKey($questionId)->whereNull('answered_at')->exists()) {
                $run->update(['outcome' => 'walked', 'completed_at' => now()]);
            }
        });
    }
}
