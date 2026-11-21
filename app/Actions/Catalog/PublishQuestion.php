<?php

namespace App\Actions\Catalog;

use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Support\Catalog\QuestionContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

            return $draft->versions()->create([
                'locale' => 'he',
                'content' => $content,
                'difficulty' => $draft->difficulty,
                'quiz_eligible' => $draft->quiz_eligible,
                'approved_by' => auth()->id(),
            ]);
        });
    }
}
