<?php

namespace App\Games\WordWheel;

use App\Models\ChildProfile;
use App\Models\WordWheelAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlayWheelAttempt
{
    public function __construct(private SpinWheel $spinWheel) {}

    /** @param 'spin'|'guess'|'solve'|'finish' $move */
    public function __invoke(ChildProfile $profile, int $id, int $revision, string $move, string $input): void
    {
        DB::transaction(function () use ($profile, $id, $revision, $move, $input): void {
            $attempt = $profile->wordWheelAttempts()->lockForUpdate()->findOrFail($id);
            $version = $attempt->version()->whereNull('withdrawn_at')->sharedLock()->firstOrFail();

            if (! $attempt->isActive() || $attempt->revision !== $revision) {
                throw ValidationException::withMessages(['round' => 'הלוח כבר השתנה. אפשר להמשיך מהמהלך הנוכחי.']);
            }

            $answer = WheelPuzzle::comparable(is_string($version->content['word'] ?? null) ? $version->content['word'] : '');
            $changes = match ($move) {
                'spin' => $this->spin($attempt),
                'guess' => $this->guess($attempt, $answer, WheelPuzzle::comparable($input)),
                'solve' => WheelPuzzle::comparable($input) === $answer
                    ? ['phase' => 'solved', 'feedback' => ['kind' => 'solved']]
                    : ['phase' => 'spin', 'feedback' => ['kind' => 'incorrect']],
                'finish' => ['phase' => 'finished', 'feedback' => ['kind' => 'finished']],
            };

            $attempt->forceFill([...$changes, 'revision' => $attempt->revision + 1])->save();
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    private function spin(WordWheelAttempt $attempt): array
    {
        if ($attempt->phase !== 'spin') {
            throw ValidationException::withMessages(['round' => 'בחרו אות לפני הסיבוב הבא.']);
        }

        $index = ($this->spinWheel)();
        $segment = SpinWheel::SEGMENTS[$index];
        $points = $segment['points'];

        return [
            'spin_index' => $index,
            'spins' => $attempt->spins + 1,
            'phase' => $points === null ? 'spin' : 'guess',
            'score' => $segment['kind'] === 'bankrupt' ? 0 : $attempt->score,
            'feedback' => $points === null ? ['kind' => $segment['kind']] : ['kind' => 'spin', 'points' => $points],
        ];
    }

    /** @return array<string, mixed> */
    private function guess(WordWheelAttempt $attempt, string $answer, string $letter): array
    {
        if ($attempt->phase !== 'guess' || $attempt->spin_index === null) {
            throw ValidationException::withMessages(['round' => 'סובבו את הגלגל לפני שבוחרים אות.']);
        }

        if (in_array($letter, $attempt->guessed_letters, true)) {
            throw ValidationException::withMessages(['letter' => 'כבר בחרתם באות הזאת.']);
        }

        $guesses = [...$attempt->guessed_letters, $letter];
        $matches = substr_count($answer, $letter);
        $points = $matches * (SpinWheel::SEGMENTS[$attempt->spin_index]['points'] ?? 0);
        $solved = str_replace([...$guesses, ' '], '', $answer) === '';

        return [
            'guessed_letters' => $guesses,
            'score' => $attempt->score + $points,
            'phase' => $solved ? 'solved' : 'spin',
            'feedback' => ['kind' => $solved ? 'solved' : ($matches > 0 ? 'hit' : 'miss'), 'matches' => $matches, 'points' => $points],
        ];
    }
}
