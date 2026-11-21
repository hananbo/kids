<?php

namespace App\Games\WordWheel;

use App\Models\ChildProfile;
use App\Models\PublishedWordEntryVersion;
use App\Models\WordEntry;
use App\Support\Catalog\CatalogLocale;
use Illuminate\Database\Eloquent\Builder;

class SelectWheelPuzzle
{
    public const GAME = 'word-wheel';

    public function __invoke(ChildProfile $profile, ?int $previousVersion = null): ?PublishedWordEntryVersion
    {
        $ownEntries = WordEntry::query()->where('team_id', $profile->team_id)->select('id');
        $versions = PublishedWordEntryVersion::query()->where('locale', CatalogLocale::BASE)
            ->where(fn (Builder $query): Builder => $query
                ->where(fn (Builder $private): Builder => $private->whereIn('word_entry_id', $ownEntries)->current())
                ->orWhere(fn (Builder $shared): Builder => $shared->whereNotIn('word_entry_id', $ownEntries)->current(shared: true)));

        $first = null;

        foreach ($versions->lazyById() as $version) {
            if (! $this->eligible($profile, $version)) {
                continue;
            }

            $first ??= $version;

            if ($previousVersion === null || $version->id > $previousVersion) {
                return $version;
            }
        }

        return $first;
    }

    private function eligible(ChildProfile $profile, PublishedWordEntryVersion $version): bool
    {
        $content = $version->content;

        if (in_array($content['wheel_eligible'] ?? true, [false, 0, '0'], true)
            || (int) ($content['target_age'] ?? 1) > $profile->learning_age) {
            return false;
        }

        $subjects = is_array($content['subjects'] ?? null) ? $content['subjects'] : [];
        if ($profile->subjects && array_intersect($profile->subjects, $subjects) === []) {
            return false;
        }

        $answer = is_string($content['word'] ?? null) ? WheelPuzzle::text($content['word']) : '';
        $letters = mb_strlen(str_replace(' ', '', $answer));

        return $letters >= 2 && $letters <= 28
            && preg_match('/^[א-ת]+(?: [א-ת]+)*$/u', $answer) === 1
            && WheelPuzzle::clue($content, $answer) !== null;
    }
}
