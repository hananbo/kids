<?php

namespace App\Models;

use App\Support\Catalog\CatalogLocale;
use App\Support\Catalog\CatalogRevision;
use App\Support\Catalog\QuestionContent;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
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
 * @property array<string, mixed> $content
 * @property array<string, array<string, mixed>>|null $localizations
 * @property int $difficulty
 * @property int $target_age
 * @property list<string>|null $subjects
 * @property bool $quiz_eligible
 * @property int|null $replaces_question_id
 * @property int|null $copied_from_version_id
 * @property list<string>|null $suppressed_locales
 * @property-read Collection<int, PublishedQuestionVersion> $livePrivateVersions
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
            'localizations' => 'array',
            'difficulty' => 'integer',
            'target_age' => 'integer',
            'subjects' => 'array',
            'quiz_eligible' => 'boolean',
            'suppressed_locales' => 'array',
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

    /** @return HasMany<PublishedQuestionVersion, $this> */
    public function livePrivateVersions(): HasMany
    {
        return $this->versions()->current();
    }

    /** @return HasOne<PublishedQuestionVersion, $this> */
    public function publishedVersion(string $locale = CatalogLocale::BASE): HasOne
    {
        return $this->hasOne(PublishedQuestionVersion::class)->where('locale', $locale)->current();
    }

    /** @return HasOne<PublishedQuestionVersion, $this> */
    public function sharedVersion(string $locale = CatalogLocale::BASE): HasOne
    {
        return $this->hasOne(PublishedQuestionVersion::class)->where('locale', $locale)->current(shared: true);
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

    /** @return array<string, mixed> */
    public function localizedDraft(string $locale): array
    {
        if ($locale === CatalogLocale::BASE) {
            return QuestionContent::normalize($this->content);
        }

        return QuestionContent::localized($this->content, $this->localizations[$locale] ?? []);
    }

    public function draftRevision(string $locale = CatalogLocale::BASE): string
    {
        return CatalogRevision::hash([
            $locale, $this->localizedDraft($locale), $this->difficulty, $this->target_age, $this->subjects, $this->quiz_eligible,
        ]);
    }
}
