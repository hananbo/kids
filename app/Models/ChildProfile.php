<?php

namespace App\Models;

use Database\Factories\ChildProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property int $learning_age
 * @property int $starting_level
 * @property list<string>|null $subjects
 * @property-read Team $team
 */
#[Fillable(['team_id', 'name', 'learning_age', 'starting_level', 'subjects'])]
class ChildProfile extends Model
{
    /** @use HasFactory<ChildProfileFactory> */
    use HasFactory;

    protected $attributes = [
        'learning_age' => 6,
        'starting_level' => 1,
    ];

    protected function casts(): array
    {
        return [
            'learning_age' => 'integer',
            'starting_level' => 'integer',
            'subjects' => 'array',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<QuizRun, $this> */
    public function quizRuns(): HasMany
    {
        return $this->hasMany(QuizRun::class);
    }

    /** @return HasMany<WordWheelAttempt, $this> */
    public function wordWheelAttempts(): HasMany
    {
        return $this->hasMany(WordWheelAttempt::class);
    }
}
