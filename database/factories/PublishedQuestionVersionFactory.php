<?php

namespace Database\Factories;

use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PublishedQuestionVersion> */
class PublishedQuestionVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'locale' => 'he',
            'content' => fn (array $attributes): array => Question::query()->whereKey($attributes['question_id'])->firstOrFail()->content,
            'difficulty' => fn (array $attributes): int => Question::query()->whereKey($attributes['question_id'])->firstOrFail()->difficulty,
            'target_age' => fn (array $attributes): int => Question::query()->whereKey($attributes['question_id'])->firstOrFail()->target_age,
            'subjects' => fn (array $attributes): ?array => Question::query()->whereKey($attributes['question_id'])->firstOrFail()->subjects,
            'quiz_eligible' => fn (array $attributes): bool => Question::query()->whereKey($attributes['question_id'])->firstOrFail()->quiz_eligible,
        ];
    }
}
