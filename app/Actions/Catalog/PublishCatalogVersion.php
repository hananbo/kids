<?php

namespace App\Actions\Catalog;

use App\Models\PublishedQuestionVersion;
use App\Models\PublishedWordEntryVersion;
use App\Models\Question;
use App\Models\WordEntry;
use App\Support\Catalog\CatalogLocale;
use App\Support\Catalog\QuestionContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PublishCatalogVersion
{
    public function __invoke(Question|WordEntry $entry, string $reviewedRevision, string $locale = CatalogLocale::BASE): PublishedQuestionVersion|PublishedWordEntryVersion
    {
        return DB::transaction(function () use ($entry, $reviewedRevision, $locale): PublishedQuestionVersion|PublishedWordEntryVersion {
            $draft = $entry->newQuery()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            Gate::authorize('update', $draft->team);

            if ($locale !== CatalogLocale::BASE && (! CatalogLocale::valid($locale) || ! isset($draft->localizations[$locale]))) {
                throw ValidationException::withMessages(['locale' => 'אין טיוטה בשפה שנבחרה.']);
            }

            if (! hash_equals($draft->draftRevision($locale), $reviewedRevision)) {
                throw ValidationException::withMessages(['revision' => $draft instanceof Question
                    ? 'השאלה השתנתה. יש לפתוח מחדש את הסקירה.'
                    : 'הערך השתנה. יש לפתוח מחדש את הסקירה.']);
            }

            $attributes = $draft instanceof Question
                ? $this->questionAttributes($draft, $locale)
                : ['content' => Validator::make($draft->localizedDraft($locale), [
                    'word' => ['required', 'string', 'max:255'],
                    'meaning' => ['required', 'string', 'max:2000'],
                    'hints' => ['nullable', 'array', 'list', 'max:10'],
                    'hints.*' => ['required', 'string', 'max:500'],
                    'wheel_eligible' => ['sometimes', 'boolean'],
                    'target_age' => ['nullable', 'integer', 'between:1,18'],
                    'subjects' => ['nullable', 'array', 'list'],
                    'subjects.*' => ['required', 'string', 'max:60'],
                ])->validate()];

            $version = $draft->versions()->create([
                'locale' => $locale,
                ...$attributes,
                'approved_by' => auth()->id(),
            ]);

            if ($draft instanceof Question && $draft->replaces_question_id !== null
                && ! in_array($locale, $draft->suppressed_locales ?? [], true)) {
                $draft->forceFill(['suppressed_locales' => [...($draft->suppressed_locales ?? []), $locale]])->save();
            }

            return $version;
        });
    }

    /** @return array<string, mixed> */
    private function questionAttributes(Question $draft, string $locale): array
    {
        return [
            'content' => QuestionContent::validate($draft->localizedDraft($locale), $draft->team_id, $draft->copiedFromVersion->content ?? []),
            ...Validator::make($draft->only(['target_age', 'subjects', 'difficulty']), [
                'target_age' => ['required', 'integer', 'between:1,18'],
                'subjects' => ['nullable', 'array', 'list'],
                'subjects.*' => ['required', 'string', 'max:60'],
                'difficulty' => ['required', 'integer', 'between:1,10'],
            ])->validate(),
            'quiz_eligible' => $draft->quiz_eligible,
        ];
    }
}
