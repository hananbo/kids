<?php

namespace App\Games\WordWheel;

class SpinWheel
{
    public const SEGMENTS = [
        ['kind' => 'points', 'points' => 100],
        ['kind' => 'points', 'points' => 200],
        ['kind' => 'bankrupt', 'points' => null],
        ['kind' => 'points', 'points' => 300],
        ['kind' => 'points', 'points' => 100],
        ['kind' => 'lose-turn', 'points' => null],
        ['kind' => 'points', 'points' => 200],
        ['kind' => 'points', 'points' => 400],
    ];

    public function __invoke(): int
    {
        return random_int(0, count(self::SEGMENTS) - 1);
    }
}
