<?php

namespace App\Games\HebrewQuiz;

final class PrizeLadder
{
    public const FULL = [100, 200, 300, 500, 1000, 2000, 4000, 8000, 16000, 32000, 64000, 125000, 250000, 500000, 1000000];

    public const SHORT = [100, 500, 1000, 4000, 16000, 32000, 125000, 1000000];

    public const GUARANTEES = [1000, 32000];

    public static function payout(int $earned, bool $lost): int
    {
        if (! $lost) {
            return $earned;
        }

        return $earned >= 32000 ? 32000 : ($earned >= 1000 ? 1000 : 0);
    }
}
