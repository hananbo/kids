<?php

namespace App\Actions\Catalog;

use App\Models\Question;
use App\Models\Team;
use App\Support\Catalog\CatalogLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RestoreSharedQuestion
{
    public function __invoke(Question $source, Team $household, string $locale = CatalogLocale::BASE): void
    {
        Gate::authorize('update', $household);

        DB::transaction(function () use ($source, $household, $locale): void {
            $replacement = $source->replacements()->whereBelongsTo($household)->lockForUpdate()->firstOrFail();

            abort_if($replacement->publishedVersion($locale)->exists(), 409, 'יש להסיר את הגרסה הפרטית לפני שחזור השאלה המקורית.');

            $replacement->forceFill([
                'suppressed_locales' => array_values(array_diff($replacement->suppressed_locales ?? [], [$locale])),
            ])->save();
        });
    }
}
