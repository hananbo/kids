<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetGameAvailability
{
    public function __invoke(Game $game, bool $enabled, ?Team $household = null): bool
    {
        Gate::authorize('update', $household ?? $game);

        if ($household === null) {
            Game::query()->whereKey($game->id)->update(['enabled' => $enabled]);

            return $enabled;
        }

        return DB::transaction(function () use ($game, $enabled, $household): bool {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);

            if (! $game->enabled) {
                throw ValidationException::withMessages(['game' => 'המשחק אינו זמין כרגע בפלטפורמה.']);
            }

            $game->households()->syncWithoutDetaching([$household->id => ['enabled' => $enabled]]);

            return $enabled;
        }, attempts: 3);
    }
}
