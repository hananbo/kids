<?php

namespace App\Actions\Catalog;

use App\Ai\Agents\AssessHebrewQuestions;
use App\Ai\Agents\EnrichHebrewQuestions;
use App\Models\Question;
use App\Models\QuestionGenerationBatch;
use App\Support\Catalog\QuestionContent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Spatie\QueueableAction\QueueableAction;
use Throwable;
use UnexpectedValueException;

class GenerateQuestionBatch
{
    use QueueableAction;

    public int $tries = 1;

    public int $timeout = 55;

    private const QUESTION_FIELDS = ['prompt', 'correct_answer', 'distractor_one', 'distractor_two', 'distractor_three'];

    public function __construct(
        private AssessHebrewQuestions $assess,
        private EnrichHebrewQuestions $enrich,
    ) {}

    public function dispatchAfterCommit(int $batchId): void
    {
        $this->onQueue()->execute($batchId)->afterCommit(); // @phpstan-ignore method.notFound (Spatie 2.17.1 gives onQueue the wrong return type for its dispatch proxy.)
    }

    public function __invoke(int $batchId): void
    {
        Cache::lock("question-generation:{$batchId}", 90)->get(function () use ($batchId): void {
            $batch = QuestionGenerationBatch::find($batchId);

            if ($batch === null || $batch->stage === 'draft') {
                return;
            }

            $batch->update(['failed_at' => null]);

            try {
                if ($batch->stage !== 'enrichment') {
                    $this->saveAssessment($batch);
                }

                $this->saveEnrichment($batch);
            } catch (Throwable $exception) {
                $batch->update(['failed_at' => now()]);

                Log::warning('Question generation failed.', [
                    'batch_id' => $batch->id,
                    'stage' => $batch->stage,
                    'exception_type' => $exception::class,
                ]);
            }
        });
    }

    private function saveAssessment(QuestionGenerationBatch $batch): void
    {
        $batch->update(['stage' => 'assessment']);

        $response = $this->assess->prompt(
            "Write {$batch->question_count} questions on this topic: {$batch->topic}",
            timeout: 20,
        );
        $items = $this->questionsFrom($response, $batch->question_count, [
            'prompt' => ['required', 'string', 'max:255'],
            'correct_answer' => ['required', 'string', 'max:255'],
            'distractor_one' => ['required', 'string', 'max:255', 'different:questions.*.correct_answer'],
            'distractor_two' => ['required', 'string', 'max:255', 'different:questions.*.correct_answer,questions.*.distractor_one'],
            'distractor_three' => ['required', 'string', 'max:255', 'different:questions.*.correct_answer,questions.*.distractor_one,questions.*.distractor_two'],
            'subjects' => ['required', 'array', 'list', 'min:1', 'max:5'],
            'subjects.*' => ['required', 'string', 'max:60'],
            'target_age' => ['required', 'integer', 'between:1,18'],
            'difficulty' => ['required', 'integer', 'between:1,10'],
            'quiz_eligible' => ['required', 'boolean'],
            'sources' => ['present', 'array', 'list', 'max:5'],
            'sources.*' => ['required', 'string', 'max:2048', 'url:http,https'],
        ]);

        DB::transaction(function () use ($batch, $items): void {
            foreach ($items as $item) {
                $batch->questions()->create([
                    'team_id' => $batch->team_id,
                    'content' => Arr::only($item, [...self::QUESTION_FIELDS, 'sources']),
                    'subjects' => $item['subjects'],
                    'target_age' => $item['target_age'],
                    'difficulty' => $item['difficulty'],
                    'quiz_eligible' => $item['quiz_eligible'],
                ]);
            }

            $batch->update(['stage' => 'enrichment']);
        });
    }

    private function saveEnrichment(QuestionGenerationBatch $batch): void
    {
        $questions = $batch->questions()->get();
        $prompts = $questions->values()->map(fn (Question $question, int $index): array => [
            'question_number' => $index + 1,
            ...array_map(QuestionContent::label(...), Arr::only($question->content, self::QUESTION_FIELDS)),
        ]);
        $response = $this->enrich->prompt($prompts->toJson(JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), timeout: 20);
        $items = $this->questionsFrom($response, $questions->count(), [
            'question_number' => ['required', 'integer', 'min:1', 'max:'.$questions->count(), 'distinct'],
            'hint' => ['present', 'nullable', 'string', 'max:1000'],
            'explanation' => ['required', 'string', 'max:2000'],
            'media_suggestion' => ['present', 'nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($batch, $questions, $items): void {
            foreach ($items as $item) {
                $question = $batch->questions()->lockForUpdate()->findOrFail($questions[$item['question_number'] - 1]->id);
                $content = $question->content;

                foreach (['hint', 'explanation'] as $field) {
                    if (blank($content[$field] ?? null) && filled($item[$field])) {
                        $content[$field] = $item[$field];
                    }
                }

                $question->forceFill([
                    'content' => $content,
                    'generation_media_suggestion' => $question->generation_media_suggestion ?? $item['media_suggestion'],
                ])->save();
            }

            $batch->update(['stage' => 'draft']);
        });
    }

    /**
     * @param  array<string, list<string>>  $rules
     * @return list<array<string, mixed>>
     */
    private function questionsFrom(mixed $response, int $count, array $rules): array
    {
        if (! $response instanceof StructuredAgentResponse) {
            throw new UnexpectedValueException('The question generation response was not structured.');
        }

        $payload = $response->toArray();
        array_walk_recursive($payload, function (mixed &$value): void {
            if (is_string($value)) {
                $value = trim($value);
            }
        });

        return Validator::make($payload, [
            'questions' => ['required', 'array', 'list', "size:{$count}"],
            'questions.*' => ['required', 'array'],
            ...Arr::mapWithKeys($rules, fn (array $fieldRules, string $field): array => ["questions.*.{$field}" => $fieldRules]),
        ])->validate()['questions'];
    }
}
