<?php

namespace App\Models;

use App\Concerns\HasCatalogVersionState;
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
 * @property array<string, mixed> $content
 * @property int $difficulty
 * @property int $target_age
 * @property list<string>|null $subjects
 * @property bool $quiz_eligible
 * @property Carbon|null $withdrawn_at
 * @property int|null $shared_from_version_id
 */
#[Fillable(['question_id', 'locale', 'content', 'difficulty', 'target_age', 'subjects', 'quiz_eligible', 'approved_by'])]
class PublishedQuestionVersion extends Model
{
    /** @use HasFactory<PublishedQuestionVersionFactory> */
    use HasCatalogVersionState, HasFactory;

    protected const CATALOG_OWNER_KEY = 'question_id';

    protected $attributes = ['target_age' => 6];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'difficulty' => 'integer',
            'target_age' => 'integer',
            'subjects' => 'array',
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
