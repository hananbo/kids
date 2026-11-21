<?php

namespace App\Actions\Catalog;

use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Support\Catalog\QuestionContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PublishQuestion
{
    public function __invoke(Question $question, string $reviewedRevision): PublishedQuestionVersion
    {
        return DB::transaction(function () use ($question, $reviewedRevision): PublishedQuestionVersion {
            $draft = Question::query()->lockForUpdate()->findOrFail($question->id);

            Gate::authorize('update', $draft->team);

            if (! hash_equals($draft->draftRevision(), $reviewedRevision)) {
                throw ValidationException::withMessages(['revision' => 'השאלה השתנתה. יש לפתוח מחדש את הסקירה.']);
            }

            $content = QuestionContent::validate($draft->content, $draft->team_id);

            $metadata = Validator::make($draft->only(['target_age', 'subjects', 'difficulty']), [
                'target_age' => ['required', 'integer', 'between:1,18'],
                'subjects' => ['nullable', 'array', 'list'],
                'subjects.*' => ['required', 'string', 'max:60'],
                'difficulty' => ['required', 'integer', 'between:1,10'],
            ])->validate();

            return $draft->versions()->create([
                'locale' => 'he',
                'content' => $content,
                ...$metadata,
                'quiz_eligible' => $draft->quiz_eligible,
                'approved_by' => auth()->id(),
            ]);
        });
    }
}
