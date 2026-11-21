<?php

use App\Actions\Catalog\GenerateQuestionBatch;
use App\Filament\Parent\Resources\QuestionGenerationBatches\Pages\ManageQuestionGenerationBatches;
use App\Models\QuestionGenerationBatch;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\QueueableAction\ActionJob;

test('question generation is queued after commit with its existing retry and timeout limits', function () {
    Queue::fake();
    $parent = User::factory()->create();
    $this->actingAs($parent);
    Filament::setCurrentPanel(Filament::getPanel('parent'));

    Livewire::test(ManageQuestionGenerationBatches::class)
        ->callAction('requestBatch', data: ['topic' => 'טבע', 'question_count' => 1])
        ->assertHasNoActionErrors();

    $batch = QuestionGenerationBatch::query()->sole();
    Queue::assertPushed(ActionJob::class, fn (ActionJob $job): bool => $job->displayName() === GenerateQuestionBatch::class
        && $job->parameters() === [$batch->id]
        && $job->afterCommit
        && $job->tries === 1
        && $job->timeout === 55);
});
