<?php

namespace App\Models;

use App\Support\Catalog\CatalogLocale;
use App\Support\Catalog\CatalogRevision;
use Database\Factories\WordEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $team_id
 * @property array<string, mixed> $content
 * @property array<string, array<string, mixed>>|null $localizations
 * @property-read Team $team
 */
#[Fillable(['team_id', 'content'])]
class WordEntry extends Model
{
    /** @use HasFactory<WordEntryFactory> */
    use HasFactory;

    public const CONTENT_FIELDS = ['word', 'meaning', 'hints'];

    protected function casts(): array
    {
        return ['content' => 'array', 'localizations' => 'array'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<PublishedWordEntryVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PublishedWordEntryVersion::class);
    }

    /** @return HasOne<PublishedWordEntryVersion, $this> */
    public function publishedVersion(string $locale = CatalogLocale::BASE): HasOne
    {
        return $this->hasOne(PublishedWordEntryVersion::class)->where('locale', $locale)->current();
    }

    /** @return array<string, mixed> */
    public function localizedDraft(string $locale = CatalogLocale::BASE): array
    {
        return $locale === CatalogLocale::BASE ? $this->content : ($this->localizations[$locale] ?? []);
    }

    public function draftRevision(string $locale = CatalogLocale::BASE): string
    {
        return CatalogRevision::hash([$locale, $this->localizedDraft($locale)]);
    }
}
