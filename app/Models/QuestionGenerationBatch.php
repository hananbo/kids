<?php

namespace App\Models;

use Database\Factories\QuestionGenerationBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $topic
 * @property int $question_count
 * @property-read int $published_questions_count
 * @property string $stage
 * @property Carbon|null $failed_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'topic', 'question_count', 'stage', 'failed_at'])]
class QuestionGenerationBatch extends Model
{
    /** @use HasFactory<QuestionGenerationBatchFactory> */
    use HasFactory;

    protected $attributes = ['stage' => 'new'];

    protected function casts(): array
    {
        return ['question_count' => 'integer', 'failed_at' => 'datetime'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('id');
    }
}
