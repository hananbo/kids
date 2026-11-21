<?php

namespace App\Actions\Catalog;

use App\Models\Question;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RestoreSharedQuestion
{
    public function __invoke(Question $source, Team $household): void
    {
        Gate::authorize('update', $household);

        DB::transaction(function () use ($source, $household): void {
            $replacement = $source->replacements()->whereBelongsTo($household)->lockForUpdate()->firstOrFail();

            abort_if($replacement->livePrivateVersions()->exists(), 409, 'יש להסיר את הגרסה הפרטית לפני שחזור השאלה המקורית.');

            $replacement->forceFill(['suppresses_original' => false])->save();
        });
    }
}
