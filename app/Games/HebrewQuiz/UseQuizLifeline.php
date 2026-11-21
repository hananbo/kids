<?php

namespace App\Games\HebrewQuiz;

use App\Models\QuizRun;
use Illuminate\Support\Facades\DB;

class UseQuizLifeline
{
    public function __invoke(QuizRun $run, int $questionId, string $kind): void
    {
        DB::transaction(function () use ($run, $questionId, $kind): void {
            $run = QuizRun::query()->lockForUpdate()->findOrFail($run->id);
            $used = $run->lifelines ?? [];

            if (! $run->isActive() || isset($used[$kind]) || ($kind === 'advisor' && $run->hint_position !== null)) {
                return;
            }

            $question = $run->questions()->where('position', $run->position)->whereNull('answered_at')->find($questionId);
            if (! $question) {
                return;
            }

            $correct = array_search('correct_answer', $question->choice_order, true);
            $others = array_values(array_diff([0, 1, 2, 3], [$correct]));
            shuffle($others);
            $eliminated = ($used['fifty']['question'] ?? null) === $questionId ? $used['fifty']['result'] : [];
            $remaining = array_values(array_diff($others, $eliminated));
            if ($kind === 'fifty') {
                $result = array_slice($others, 0, 2);
            } elseif ($kind === 'advisor') {
                $confidence = max(60, 95 - 2 * $run->position);
                $result = [random_int(1, 100) <= $confidence ? $correct : $remaining[0]];
            } else {
                $votes = [0, 0, 0, 0];
                // Each simulated voter either recognizes the answer or guesses among the visible options.
                $options = [...$remaining, $correct];
                $confidence = max(12, 64 - 4 * $run->position);
                for ($voter = 0; $voter < 100; $voter++) {
                    $slot = random_int(1, 100) <= $confidence ? $correct : $options[array_rand($options)];
                    $votes[$slot]++;
                }
                $result = $votes;
            }
            $used[$kind] = ['question' => $questionId, 'result' => $result];
            $run->update(['lifelines' => $used]);
        });
    }
}
