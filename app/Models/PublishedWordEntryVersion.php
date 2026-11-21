<?php

namespace App\Models;

use App\Concerns\HasCatalogVersionState;
use Database\Factories\PublishedWordEntryVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $word_entry_id
 * @property string $locale
 * @property array<string, mixed> $content
 * @property int|null $shared_from_version_id
 * @property Carbon|null $withdrawn_at
 * @property-read WordEntry $wordEntry
 */
#[Fillable(['word_entry_id', 'locale', 'content', 'approved_by'])]
class PublishedWordEntryVersion extends Model
{
    /** @use HasFactory<PublishedWordEntryVersionFactory> */
    use HasCatalogVersionState, HasFactory;

    protected const CATALOG_OWNER_KEY = 'word_entry_id';

    protected function casts(): array
    {
        return ['content' => 'array', 'withdrawn_at' => 'datetime'];
    }

    /** @return BelongsTo<WordEntry, $this> */
    public function wordEntry(): BelongsTo
    {
        return $this->belongsTo(WordEntry::class);
    }
}
