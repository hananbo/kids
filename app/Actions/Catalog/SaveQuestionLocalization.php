<?php

namespace App\Actions\Catalog;

use App\Models\Question;
use App\Support\Catalog\CatalogLocale;
use App\Support\Catalog\QuestionContent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveQuestionLocalization
{
    /** @param array<string, mixed> $content */
    public function __invoke(Question $question, string $locale, array $content): void
    {
        if (! CatalogLocale::valid($locale) || $locale === CatalogLocale::BASE) {
            throw ValidationException::withMessages(['locale' => 'הזינו קוד שפה נוסף, למשל en.']);
        }

        DB::transaction(function () use ($question, $locale, $content): void {
            $draft = Question::query()->lockForUpdate()->findOrFail($question->id);
            Gate::authorize('update', $draft->team);

            $localizations = $draft->localizations ?? [];
            $localizations[$locale] = Arr::only($content, [
                ...QuestionContent::FIELDS, 'explanation', 'hint', 'sources',
            ]);
            $draft->forceFill(['localizations' => $localizations])->save();
        }, attempts: 3);
    }
}
