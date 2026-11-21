<?php

namespace App\Models;

use Database\Factories\QuizRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $child_profile_id
 * @property string $game
 * @property int $position
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable(['child_profile_id', 'game', 'position', 'completed_at'])]
class QuizRun extends Model
{
    /** @use HasFactory<QuizRunFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('completed_at')->whereNull('cancelled_at');
    }

    public function isActive(): bool
    {
        return $this->completed_at === null && $this->cancelled_at === null;
    }

    /** @return BelongsTo<ChildProfile, $this> */
    public function childProfile(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class);
    }

    /** @return HasMany<QuizRunQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizRunQuestion::class)->orderBy('position');
    }
}
