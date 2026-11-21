<?php

namespace App\Actions\Catalog;

use App\Models\Question;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;

class CopySharedQuestion
{
    public function __invoke(Question $source, Team $household): Question
    {
        Gate::authorize('update', $household);

        $version = $source->sharedVersion()->firstOrFail();

        return $source->replacements()->firstOrCreate(
            ['team_id' => $household->id],
            ['copied_from_version_id' => $version->id, ...$version->only([
                'content', 'difficulty', 'quiz_eligible', 'target_age', 'subjects',
            ])],
        );
    }
}
