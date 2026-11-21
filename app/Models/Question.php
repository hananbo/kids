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
 * @property int|null $question_generation_batch_id
 * @property string|null $generation_media_suggestion
 * @property array<string, string|list<string>|null> $content
 * @property int $difficulty
 * @property int $target_age
 * @property list<string>|null $subjects
 * @property bool $quiz_eligible
 * @property int|null $replaces_question_id
 * @property int|null $copied_from_version_id
 * @property bool $suppresses_original
 * @property-read PublishedQuestionVersion|null $copiedFromVersion
 * @property-read Team $team
 * @property-read PublishedQuestionVersion|null $publishedVersion
 * @property-read PublishedQuestionVersion|null $sharedVersion
 */
#[Fillable(['copied_from_version_id', 'team_id', 'content', 'difficulty', 'target_age', 'subjects', 'quiz_eligible', 'generation_media_suggestion'])]
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
            'suppresses_original' => 'boolean',
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
            ->ofMany(['id' => 'max'], fn (Builder $query) => $query
                ->where('locale', 'he')
                ->whereNull('shared_from_version_id'))
            ->whereNull('published_question_versions.withdrawn_at');
    }

    /** @return HasOne<PublishedQuestionVersion, $this> */
    public function sharedVersion(): HasOne
    {
        return $this->hasOne(PublishedQuestionVersion::class)
            ->ofMany(['shared_from_version_id' => 'max', 'id' => 'max'], fn (Builder $query) => $query
                ->where('locale', 'he')
                ->whereNotNull('shared_from_version_id'))
            ->whereNull('published_question_versions.withdrawn_at');
    }

    /** @return HasMany<Question, $this> */
    public function replacements(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_question_id');
    }

    /** @return BelongsTo<PublishedQuestionVersion, $this> */
    public function copiedFromVersion(): BelongsTo
    {
        return $this->belongsTo(PublishedQuestionVersion::class, 'copied_from_version_id');
    }

    public function draftRevision(): string
    {
        return hash('sha256', serialize([$this->content, $this->difficulty, $this->target_age, $this->subjects, $this->quiz_eligible]));
    }
}
