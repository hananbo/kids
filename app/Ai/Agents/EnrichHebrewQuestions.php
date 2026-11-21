<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class EnrichHebrewQuestions implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'Enrich each numbered Hebrew learning question. Return the question number and a short Hebrew explanation of the expected answer. Provide a Hebrew hint that does not give away the answer, and describe useful media in Hebrew without including URLs or asset paths. Use empty strings for optional suggestions when they add no value.';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()->items($schema->object(fn (JsonSchema $item): array => [
                'question_number' => $item->integer()->required(),
                'hint' => $item->string()->required(),
                'explanation' => $item->string()->required(),
                'media_suggestion' => $item->string()->required(),
            ]))->required(),
        ];
    }
}
