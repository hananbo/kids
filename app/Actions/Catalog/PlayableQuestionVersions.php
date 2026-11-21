<?php

namespace App\Actions\Catalog;

use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Support\Catalog\CatalogLocale;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PlayableQuestionVersions
{
    /**
     * @param  Closure(Builder<PublishedQuestionVersion>): mixed  $eligible
     * @return Collection<int, PublishedQuestionVersion>
     */
    public function __invoke(int $teamId, Closure $eligible, string $locale = CatalogLocale::BASE): Collection
    {
        $available = Question::query()
            ->whereDoesntHave('replacements', fn (Builder $query): Builder => $query
                ->where('team_id', $teamId)
                ->whereJsonContains('suppressed_locales', $locale));

        $versions = PublishedQuestionVersion::query()->where('locale', $locale)->tap($eligible);
        $private = (clone $versions)
            ->whereIn('question_id', (clone $available)->where('team_id', $teamId)->select('id'))
            ->current()
            ->get();

        $shared = (clone $versions)
            ->whereIn('question_id', $available->where('team_id', '!=', $teamId)->select('id'))
            ->current(shared: true)
            ->get();

        return $private->toBase()->concat($shared)->sortByDesc('question_id')->values();
    }
}
