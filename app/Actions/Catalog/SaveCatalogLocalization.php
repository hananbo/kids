<?php

namespace App\Actions\Catalog;

use App\Models\Question;
use App\Models\WordEntry;
use App\Support\Catalog\CatalogLocale;
use App\Support\Catalog\QuestionContent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveCatalogLocalization
{
    /** @param array<string, mixed> $content */
    public function __invoke(Question|WordEntry $entry, string $locale, array $content): void
    {
        if (! CatalogLocale::valid($locale) || $locale === CatalogLocale::BASE) {
            throw ValidationException::withMessages(['locale' => 'הזינו קוד שפה נוסף, למשל en.']);
        }

        DB::transaction(function () use ($entry, $locale, $content): void {
            $draft = $entry->newQuery()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $draft->team);

            $localizations = $draft->localizations ?? [];
            $localizations[$locale] = Arr::only($content, $draft instanceof Question
                ? [...QuestionContent::FIELDS, 'explanation', 'hint', 'sources']
                : WordEntry::CONTENT_FIELDS);
            $draft->forceFill(['localizations' => $localizations])->save();
        }, attempts: 3);
    }
}
