<?php

namespace App\Filament\Parent\Resources\QuestionGenerationBatches\Pages;

use App\Filament\Parent\Resources\QuestionGenerationBatches\QuestionGenerationBatchResource;
use App\Jobs\ProcessQuestionGenerationBatch;
use App\Models\QuestionGenerationBatch;
use App\Models\Team;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;

class ManageQuestionGenerationBatches extends ManageRecords
{
    protected static string $resource = QuestionGenerationBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestBatch')
                ->label('יצירת אצווה')
                ->extraAttributes(['data-test' => 'request-question-batch'])
                ->schema([
                    TextInput::make('topic')
                        ->label('נושא לשאלות')
                        ->required()
                        ->maxLength(255)
                        ->extraInputAttributes(['data-test' => 'generation-topic']),
                    TextInput::make('question_count')
                        ->label('מספר שאלות')
                        ->required()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(5)
                        ->default(3)
                        ->extraInputAttributes(['data-test' => 'generation-count']),
                ])
                ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-test' => 'submit-question-batch']))
                ->action(function (array $data): void {
                    $team = Filament::auth()->user()?->currentTeam;
                    abort_unless($team instanceof Team, 403);
                    Gate::authorize('update', $team);

                    $batch = QuestionGenerationBatch::create([
                        'team_id' => $team->id,
                        'topic' => $data['topic'],
                        'question_count' => $data['question_count'],
                    ]);

                    ProcessQuestionGenerationBatch::dispatch($batch->id)->afterCommit();
                }),
        ];
    }
}
