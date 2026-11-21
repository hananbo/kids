<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Question> */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'content' => [
                'prompt' => fake()->unique()->sentence(),
                'correct_answer' => 'אדום',
                'distractor_one' => 'כחול',
                'distractor_two' => 'ירוק',
                'distractor_three' => 'לבן',
                'explanation' => 'אדום היא התשובה הנכונה.',
            ],
            'difficulty' => 1,
            'quiz_eligible' => true,
        ];
    }
}
