<?php

namespace App\Games\HebrewQuiz;

use App\Models\ChildProfile;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SelectQuizQuestions
{
    public const GAME = 'hebrew-quiz';

    /** @return Collection<int, PublishedQuestionVersion> */
    public function __invoke(ChildProfile $profile): Collection
    {
        $versions = Question::query()
            ->where('team_id', $profile->team_id)
            ->withWhereHas('publishedVersion', fn (Builder|HasOne $query) => $query->where('quiz_eligible', true))
            ->latest('id')
            ->get()
            ->pluck('publishedVersion')
            ->groupBy('difficulty');

        $selected = collect();

        foreach ([1 => 3, 2 => 3, 3 => 2] as $difficulty => $quantity) {
            $group = $versions->get($difficulty, collect())->take($quantity);

            if ($group->count() < $quantity) {
                throw ValidationException::withMessages(['pool' => 'אין מספיק שאלות מאושרות ברמת קושי '.$difficulty.'.']);
            }

            $selected = $selected->concat($group);
        }

        return $selected;
    }
}
