<?php

namespace App\Models;

use Database\Factories\PublishedQuestionVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $question_id
 * @property string $locale
 * @property array<string, string> $content
 * @property int $difficulty
 * @property bool $quiz_eligible
 * @property Carbon|null $withdrawn_at
 */
#[Fillable(['question_id', 'locale', 'content', 'difficulty', 'quiz_eligible', 'approved_by'])]
class PublishedQuestionVersion extends Model
{
    /** @use HasFactory<PublishedQuestionVersionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'difficulty' => 'integer',
            'quiz_eligible' => 'boolean',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
