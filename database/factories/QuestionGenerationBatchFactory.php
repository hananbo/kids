<?php

namespace Database\Factories;

use App\Models\QuestionGenerationBatch;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuestionGenerationBatch> */
class QuestionGenerationBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'topic' => 'טבע',
            'question_count' => 1,
        ];
    }
}
