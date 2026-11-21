<?php

namespace App\Actions\Catalog;

use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class PlayableQuestionVersions
{
    /**
     * @param  Closure(Builder<PublishedQuestionVersion>|HasOne<PublishedQuestionVersion, Question>): mixed  $eligible
     * @return Collection<int, PublishedQuestionVersion>
     */
    public function __invoke(int $teamId, Closure $eligible): Collection
    {
        $available = Question::query()
            ->whereDoesntHave('replacements', fn (Builder $query): Builder => $query
                ->where('team_id', $teamId)
                ->where('suppresses_original', true));

        $private = (clone $available)
            ->where('team_id', $teamId)
            ->withWhereHas('publishedVersion', $eligible)
            ->get()
            ->toBase()
            ->map(fn (Question $question): ?PublishedQuestionVersion => $question->publishedVersion);

        $shared = (clone $available)
            ->where('team_id', '!=', $teamId)
            ->withWhereHas('sharedVersion', $eligible)
            ->get()
            ->toBase()
            ->map(fn (Question $question): ?PublishedQuestionVersion => $question->sharedVersion);

        return $private->concat($shared)->filter()->sortByDesc('question_id')->values();
    }
}
