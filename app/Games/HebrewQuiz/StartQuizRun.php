<?php

namespace App\Games\HebrewQuiz;

use App\Models\ChildProfile;
use App\Models\QuizRun;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StartQuizRun
{
    public function __construct(private SelectQuizQuestions $selectQuestions) {}

    public function __invoke(ChildProfile $profile): QuizRun
    {
        return DB::transaction(function () use ($profile): QuizRun {
            $profile = ChildProfile::query()->lockForUpdate()->findOrFail($profile->id);

            $active = $profile->quizRuns()
                ->where('game', SelectQuizQuestions::GAME)
                ->whereNull('completed_at')
                ->latest('id')
                ->first();

            if ($active) {
                return $active;
            }

            $versions = ($this->selectQuestions)($profile);
            $run = $profile->quizRuns()->create(['game' => SelectQuizQuestions::GAME]);

            foreach ($versions as $position => $version) {
                $run->questions()->create([
                    'published_question_version_id' => $version->id,
                    'position' => $position,
                    'choice_order' => Arr::shuffle(RecordQuizAnswer::CHOICES),
                ]);
            }

            return $run;
        });
    }
}
