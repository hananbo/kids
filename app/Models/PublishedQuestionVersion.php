<?php

namespace App\Models;

use Database\Factories\PublishedQuestionVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
    use HasFactory;

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

    /** @param Builder<self> $query */
    #[Scope]
    protected function current(Builder $query, bool $shared = false): void
    {
        $query->whereNull('published_question_versions.withdrawn_at')
            ->where('published_question_versions.id', '=', function (QueryBuilder $candidate) use ($shared): void {
                $candidate->from('published_question_versions as candidate')
                    ->select('candidate.id')
                    ->whereColumn('candidate.question_id', 'published_question_versions.question_id')
                    ->whereColumn('candidate.locale', 'published_question_versions.locale')
                    ->whereNull('candidate.shared_from_version_id', not: $shared)
                    ->orderByDesc($shared ? 'candidate.shared_from_version_id' : 'candidate.id')
                    ->limit(1);
            });
    }

    /** @return HasOne<self, $this> */
    public function sharedCopy(): HasOne
    {
        return $this->hasOne(self::class, 'shared_from_version_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
