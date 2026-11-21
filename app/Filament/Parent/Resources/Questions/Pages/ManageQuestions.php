<?php

namespace App\Filament\Parent\Resources\Questions\Pages;

use App\Filament\Parent\Resources\Questions\QuestionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageQuestions extends ManageRecords
{
    protected static string $resource = QuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('שאלה חדשה')
                ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-test' => 'create-question-submit']))
                ->mutateDataUsing(fn (array $data): array => ['team_id' => Filament::auth()->user()?->current_team_id] + $data),
        ];
    }
}
