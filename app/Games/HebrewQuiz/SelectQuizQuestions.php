<?php

namespace App\Games\HebrewQuiz;

use App\Models\ChildProfile;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Models\QuizRunQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SelectQuizQuestions
{
    public const GAME = 'hebrew-quiz';

    private const LEVEL_GROUPS = [
        1 => [[[1], 3], [[2], 3], [[3], 2]],
        2 => [[[4], 3], [[5], 3], [[6], 2]],
        3 => [[[7], 3], [[8, 9], 3], [[10], 2]],
    ];

    /** @return Collection<int, PublishedQuestionVersion> */
    public function __invoke(ChildProfile $profile): Collection
    {
        $groups = $this->questionGroups($profile);
        $shortages = $this->shortages($groups);

        if ($shortages !== []) {
            throw ValidationException::withMessages(['pool' => implode(' ', $shortages)]);
        }

        $lastSeen = $this->lastSeen($profile);
        $selected = collect();

        foreach ($groups as $group) {
            [$seen, $unseen] = $group['versions']->partition(
                fn (PublishedQuestionVersion $version): bool => $lastSeen->has($version->question_id),
            );

            $ordered = $unseen->sortByDesc('target_age')->concat(
                $seen->sortBy(fn (PublishedQuestionVersion $version): string => $lastSeen->get($version->question_id)),
            );

            $selected = $selected->concat($ordered->take($group['required']));
        }

        return $selected;
    }

    /** @return list<string> */
    public function poolShortages(ChildProfile $profile): array
    {
        return $this->shortages($this->questionGroups($profile));
    }

    /** @return list<array{difficulties: list<int>, required: int, versions: Collection<int, PublishedQuestionVersion>}> */
    private function questionGroups(ChildProfile $profile): array
    {
        $versions = Question::query()
            ->where('team_id', $profile->team_id)
            ->withWhereHas('publishedVersion', fn (Builder|HasOne $query) => $query
                ->where('quiz_eligible', true)
                ->where('target_age', '<=', $profile->learning_age))
            ->latest('id')
            ->get()
            ->toBase()
            ->map(fn (Question $question): ?PublishedQuestionVersion => $question->publishedVersion)
            ->filter()
            ->values();

        if ($profile->subjects) {
            $versions = $versions->filter(
                fn (PublishedQuestionVersion $version): bool => array_intersect($profile->subjects, $version->subjects ?? []) !== [],
            );
        }

        return array_values(array_map(fn (array $group): array => [
            'difficulties' => $group[0],
            'required' => $group[1],
            'versions' => $versions->filter(
                fn (PublishedQuestionVersion $version): bool => in_array($version->difficulty, $group[0], true),
            ),
        ], self::LEVEL_GROUPS[$profile->starting_level]));
    }

    /**
     * @param  list<array{difficulties: list<int>, required: int, versions: Collection<int, PublishedQuestionVersion>}>  $groups
     * @return list<string>
     */
    private function shortages(array $groups): array
    {
        $shortages = [];

        foreach ($groups as $group) {
            $available = $group['versions']->count();

            if ($available < $group['required']) {
                $difficulty = implode('–', $group['difficulties']);
                $shortages[] = "רמת קושי {$difficulty}: {$available} מתוך {$group['required']} שאלות מאושרות.";
            }
        }

        return $shortages;
    }

    /** @return Collection<int, string> */
    private function lastSeen(ChildProfile $profile): Collection
    {
        return QuizRunQuestion::query()
            ->join('quiz_runs', 'quiz_runs.id', '=', 'quiz_run_questions.quiz_run_id')
            ->join('published_question_versions', 'published_question_versions.id', '=', 'quiz_run_questions.published_question_version_id')
            ->where('quiz_runs.child_profile_id', $profile->id)
            ->where('quiz_runs.game', self::GAME)
            ->whereColumn('quiz_run_questions.position', '<=', 'quiz_runs.position')
            ->groupBy('published_question_versions.question_id')
            ->select('published_question_versions.question_id')
            ->selectRaw('MAX(COALESCE(quiz_run_questions.answered_at, quiz_runs.updated_at)) AS last_seen')
            ->pluck('last_seen', 'question_id');
    }
}
