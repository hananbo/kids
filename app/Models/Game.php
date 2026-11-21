<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property bool $enabled
 */
#[Fillable(['key', 'name', 'enabled'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /** @return BelongsToMany<Team, $this> */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withPivot('enabled');
    }

    public static function allowsStart(string $key, int $teamId, bool $lock = false): bool
    {
        $games = self::query()->where('key', $key);
        $game = ($lock ? $games->sharedLock() : $games)->first();

        if (! $game?->enabled) {
            return false;
        }

        $disabled = $game->households()->whereKey($teamId)->wherePivot('enabled', false);

        return $lock
            ? $disabled->sharedLock()->first(['teams.id']) === null
            : ! $disabled->exists();
    }
}
