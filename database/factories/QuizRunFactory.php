<?php

namespace Database\Factories;

use App\Games\HebrewQuiz\SelectQuizQuestions;
use App\Models\ChildProfile;
use App\Models\QuizRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuizRun> */
class QuizRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'child_profile_id' => ChildProfile::factory(),
            'game' => SelectQuizQuestions::GAME,
            'position' => 0,
        ];
    }
}
