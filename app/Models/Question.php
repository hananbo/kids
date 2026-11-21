<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $team_id
 * @property array<string, mixed> $content
 * @property int $difficulty
 * @property int $target_age
 * @property list<string>|null $subjects
 * @property bool $quiz_eligible
 * @property-read Team $team
 * @property-read PublishedQuestionVersion|null $publishedVersion
 */
#[Fillable(['team_id', 'content', 'difficulty', 'target_age', 'subjects', 'quiz_eligible'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
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
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<PublishedQuestionVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PublishedQuestionVersion::class);
    }

    /** @return HasOne<PublishedQuestionVersion, $this> */
    public function publishedVersion(): HasOne
    {
        return $this->hasOne(PublishedQuestionVersion::class)
            ->ofMany(['id' => 'max'], fn (Builder $query) => $query->where('locale', 'he'))
            ->whereNull('published_question_versions.withdrawn_at');
    }

    public function draftRevision(): string
    {
        return hash('sha256', serialize([$this->content, $this->difficulty, $this->target_age, $this->subjects, $this->quiz_eligible]));
    }
}
