<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\WordEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WordEntry> */
class WordEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'content' => ['word' => fake()->unique()->word(), 'meaning' => fake()->sentence()],
        ];
    }
}
