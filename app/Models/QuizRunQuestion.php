<?php

namespace App\Models;

use Database\Factories\QuizRunQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiz_run_id
 * @property int $published_question_version_id
 * @property int $position
 * @property list<string> $choice_order
 * @property int|null $selected_choice
 * @property bool|null $is_correct
 * @property Carbon|null $answered_at
 * @property-read PublishedQuestionVersion $version
 */
#[Fillable(['quiz_run_id', 'published_question_version_id', 'position', 'choice_order', 'selected_choice', 'is_correct', 'answered_at'])]
class QuizRunQuestion extends Model
{
    /** @use HasFactory<QuizRunQuestionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'choice_order' => 'array',
            'position' => 'integer',
            'selected_choice' => 'integer',
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<QuizRun, $this> */
    public function quizRun(): BelongsTo
    {
        return $this->belongsTo(QuizRun::class);
    }

    /** @return BelongsTo<PublishedQuestionVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PublishedQuestionVersion::class, 'published_question_version_id');
    }
}
