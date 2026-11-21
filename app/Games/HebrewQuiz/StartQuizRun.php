<?php

namespace App\Games\HebrewQuiz;

use App\Models\ChildProfile;
use App\Models\PublishedQuestionVersion;
use App\Models\QuizRun;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartQuizRun
{
    public function __construct(
        private SelectQuizQuestions $selectQuestions,
        private QuizProgress $progress,
    ) {}

    public function __invoke(ChildProfile $profile): QuizRun
    {
        return DB::transaction(function () use ($profile): QuizRun {
            $profile = ChildProfile::query()->lockForUpdate()->findOrFail($profile->id);

            $active = $profile->quizRuns()
                ->where('game', SelectQuizQuestions::GAME)
                ->active()
                ->latest('id')
                ->first();

            if ($active) {
                return $active;
            }

            $level = $this->progress->highestLevel($profile);
            $versions = ($this->selectQuestions)($profile, $level);
            $availableVersions = PublishedQuestionVersion::query()
                ->whereKey($versions->pluck('id')->all())
                ->whereNull('withdrawn_at')
                ->orderBy('id')
                ->sharedLock()
                ->get(['id']);

            if ($availableVersions->count() !== $versions->count()) {
                throw ValidationException::withMessages(['pool' => 'מאגר השאלות השתנה. נסו להתחיל שוב.']);
            }

            $run = $profile->quizRuns()->create(['game' => SelectQuizQuestions::GAME, 'level' => $level]);

            foreach ($versions as $position => $version) {
                $run->questions()->create([
                    'published_question_version_id' => $version->id,
                    'position' => $position,
                    'choice_order' => Arr::shuffle(RecordQuizAnswer::CHOICES),
                ]);
            }

            return $run;
        }, attempts: 3);
    }
}
