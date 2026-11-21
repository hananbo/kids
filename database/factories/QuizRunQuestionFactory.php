<?php

namespace Database\Factories;

use App\Games\HebrewQuiz\RecordQuizAnswer;
use App\Models\PublishedQuestionVersion;
use App\Models\QuizRun;
use App\Models\QuizRunQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuizRunQuestion> */
class QuizRunQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_run_id' => QuizRun::factory(),
            'published_question_version_id' => PublishedQuestionVersion::factory(),
            'position' => 0,
            'choice_order' => RecordQuizAnswer::CHOICES,
        ];
    }
}
