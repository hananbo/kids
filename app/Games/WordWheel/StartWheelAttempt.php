<?php

namespace App\Games\WordWheel;

use App\Models\ChildProfile;
use App\Models\Game;
use App\Models\WordWheelAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartWheelAttempt
{
    public function __construct(private SelectWheelPuzzle $selectPuzzle) {}

    public function __invoke(ChildProfile $profile): WordWheelAttempt
    {
        return DB::transaction(function () use ($profile): WordWheelAttempt {
            $profile = ChildProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $latest = $profile->wordWheelAttempts()->with('version')->latest('id')->first();

            if ($latest?->isActive() && $latest->version && $latest->version->withdrawn_at === null) {
                return $latest;
            }

            if (! Game::allowsStart(SelectWheelPuzzle::GAME, $profile->team_id, lock: true)) {
                throw ValidationException::withMessages(['game' => 'המשחק אינו זמין כרגע.']);
            }

            $puzzle = ($this->selectPuzzle)($profile, $latest?->published_word_entry_version_id);
            if ($puzzle === null || ! $puzzle->newQuery()->whereKey($puzzle->id)->whereNull('withdrawn_at')->sharedLock()->first(['id'])) {
                throw ValidationException::withMessages(['pool' => 'אין כרגע מילים מאושרות שמתאימות לפרופיל.']);
            }

            return $profile->wordWheelAttempts()->create(['published_word_entry_version_id' => $puzzle->id]);
        }, attempts: 3);
    }
}
