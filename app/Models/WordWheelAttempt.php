<?php

namespace App\Models;

use Database\Factories\WordWheelAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $child_profile_id
 * @property int|null $published_word_entry_version_id
 * @property string $phase
 * @property int $revision
 * @property int $score
 * @property int|null $spin_index
 * @property int $spins
 * @property list<string> $guessed_letters
 * @property array{kind: string, matches?: int, points?: int}|null $feedback
 * @property-read PublishedWordEntryVersion|null $version
 */
#[Fillable(['published_word_entry_version_id'])]
class WordWheelAttempt extends Model
{
    /** @use HasFactory<WordWheelAttemptFactory> */
    use HasFactory;

    protected $attributes = [
        'guessed_letters' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'score' => 'integer',
            'spin_index' => 'integer',
            'spins' => 'integer',
            'guessed_letters' => 'array',
            'feedback' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->phase, ['spin', 'guess'], true);
    }

    /** @return BelongsTo<PublishedWordEntryVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PublishedWordEntryVersion::class, 'published_word_entry_version_id');
    }

    /** @return BelongsTo<ChildProfile, $this> */
    public function childProfile(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class);
    }
}
