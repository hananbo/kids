<?php

namespace App\Actions\Catalog;

use App\Models\PublishedQuestionVersion;
use App\Models\Question;
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

            $content = Validator::make(
                array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $draft->content),
                [
                    'prompt' => ['required', 'string', 'max:255'],
                    'correct_answer' => ['required', 'string', 'max:255'],
                    'distractor_one' => ['required', 'string', 'max:255', 'different:correct_answer'],
                    'distractor_two' => ['required', 'string', 'max:255', 'different:correct_answer,distractor_one'],
                    'distractor_three' => ['required', 'string', 'max:255', 'different:correct_answer,distractor_one,distractor_two'],
                    'explanation' => ['required', 'string', 'max:2000'],
                ],
            )->validate();

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
