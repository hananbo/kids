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
 * @property string|null $outcome
 * @property array<string, array{question: int, result: list<int>}>|null $lifelines
 * @property int $position
 * @property int $level
 * @property int|null $unlocked_level
 * @property int|null $hint_position
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property-read ChildProfile $childProfile
 */
#[Fillable(['child_profile_id', 'game', 'position', 'level', 'unlocked_level', 'hint_position', 'outcome', 'lifelines', 'completed_at'])]
class QuizRun extends Model
{
    /** @use HasFactory<QuizRunFactory> */
    use HasFactory;

    protected $attributes = ['level' => 1];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'lifelines' => 'array',
            'level' => 'integer',
            'unlocked_level' => 'integer',
            'hint_position' => 'integer',
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

    public function canRevealHintFor(QuizRunQuestion $question): bool
    {
        return $this->isActive()
            && $this->hint_position === null
            && ! isset($this->lifelines['advisor'])
            && $question->quiz_run_id === $this->id
            && $question->position === $this->position
            && $question->answered_at === null
            && $question->approvedHint() !== null;
    }

    public function stars(): int
    {
        return $this->questions()->where('is_correct', true)->count();
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
