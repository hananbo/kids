<?php

namespace App\Actions\Catalog;

use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Support\Catalog\CatalogLocale;
use App\Support\Catalog\QuestionContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PublishQuestion
{
    public function __invoke(Question $question, string $reviewedRevision, string $locale = CatalogLocale::BASE): PublishedQuestionVersion
    {
        return DB::transaction(function () use ($question, $reviewedRevision, $locale): PublishedQuestionVersion {
            $draft = Question::query()->lockForUpdate()->findOrFail($question->id);

            Gate::authorize('update', $draft->team);

            if ($locale !== CatalogLocale::BASE && (! CatalogLocale::valid($locale) || ! isset($draft->localizations[$locale]))) {
                throw ValidationException::withMessages(['locale' => 'אין טיוטה בשפה שנבחרה.']);
            }

            if (! hash_equals($draft->draftRevision($locale), $reviewedRevision)) {
                throw ValidationException::withMessages(['revision' => 'השאלה השתנתה. יש לפתוח מחדש את הסקירה.']);
            }

            $content = QuestionContent::validate($draft->localizedDraft($locale), $draft->team_id, $draft->copiedFromVersion->content ?? []);

            $metadata = Validator::make($draft->only(['target_age', 'subjects', 'difficulty']), [
                'target_age' => ['required', 'integer', 'between:1,18'],
                'subjects' => ['nullable', 'array', 'list'],
                'subjects.*' => ['required', 'string', 'max:60'],
                'difficulty' => ['required', 'integer', 'between:1,10'],
            ])->validate();

            $version = $draft->versions()->create([
                'locale' => $locale,
                'content' => $content,
                ...$metadata,
                'quiz_eligible' => $draft->quiz_eligible,
                'approved_by' => auth()->id(),
            ]);

            if ($draft->replaces_question_id !== null && ! $draft->suppresses_original) {
                $draft->forceFill(['suppresses_original' => true])->save();
            }

            return $version;
        });
    }
}
