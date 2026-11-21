<?php

namespace App\Games\HebrewQuiz;

use App\Models\ChildProfile;
use App\Models\QuizRun;
use App\Models\QuizRunQuestion;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizProgress
{
    private const MAX_LEVEL = 3;

    private const QUALIFYING_STARS = 6;

    public function highestLevel(ChildProfile $profile): int
    {
        return max($profile->starting_level, (int) $this->runs($profile)->max('unlocked_level'));
    }

    public function completedStars(ChildProfile $profile): int
    {
        return QuizRunQuestion::query()
            ->where('is_correct', true)
            ->whereIn('quiz_run_id', $this->runs($profile)->whereNotNull('completed_at')->select('quiz_runs.id'))
            ->count();
    }

    public function unlockedLevelAfter(QuizRun $run): ?int
    {
        if ($run->level >= self::MAX_LEVEL || $run->stars() < self::QUALIFYING_STARS) {
            return null;
        }

        $profile = $run->childProfile;
        if ($run->level !== $this->highestLevel($profile)) {
            return null;
        }

        $previous = $this->runs($profile)->whereNotNull('completed_at')->latest('completed_at')->latest('id')->first();

        return $previous?->level === $run->level && $previous->stars() >= self::QUALIFYING_STARS
            ? $run->level + 1
            : null;
    }

    /** @return HasMany<QuizRun, ChildProfile> */
    private function runs(ChildProfile $profile): HasMany
    {
        return $profile->quizRuns()->where('game', SelectQuizQuestions::GAME);
    }
}
