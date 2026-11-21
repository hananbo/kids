<?php

namespace Database\Factories;

use App\Models\ChildProfile;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChildProfile> */
class ChildProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->firstName(),
        ];
    }
}
