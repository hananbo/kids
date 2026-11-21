<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class AssessHebrewQuestions implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'Write original Hebrew learning questions for children on the given topic. Each has one correct answer and three distinct distractors. Suggest subject tags, target age (1–18), difficulty (1–10), and whether each question is suitable for a four-choice quiz. Provide only source URLs you have good reason to trust. Leave sources empty when unsure.';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()->items($schema->object(fn (JsonSchema $item): array => [
                'prompt' => $item->string()->required(),
                'correct_answer' => $item->string()->required(),
                'distractor_one' => $item->string()->required(),
                'distractor_two' => $item->string()->required(),
                'distractor_three' => $item->string()->required(),
                'subjects' => $item->array()->items($item->string())->required(),
                'target_age' => $item->integer()->min(1)->max(18)->required(),
                'difficulty' => $item->integer()->min(1)->max(10)->required(),
                'quiz_eligible' => $item->boolean()->required(),
                'sources' => $item->array()->items($item->string())->required(),
            ]))->required(),
        ];
    }
}
