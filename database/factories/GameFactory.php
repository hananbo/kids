<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Game> */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(),
            'name' => fake()->words(2, true),
            'enabled' => true,
        ];
    }
}
