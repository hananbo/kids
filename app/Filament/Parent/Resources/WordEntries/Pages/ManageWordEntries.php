<?php

namespace App\Filament\Parent\Resources\WordEntries\Pages;

use App\Filament\Parent\Resources\WordEntries\WordEntryResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageWordEntries extends ManageRecords
{
    protected static string $resource = WordEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('ערך מילוני חדש')
                ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-test' => 'create-word-entry']))
                ->mutateDataUsing(fn (array $data): array => ['team_id' => Filament::auth()->user()?->current_team_id] + $data),
        ];
    }
}
