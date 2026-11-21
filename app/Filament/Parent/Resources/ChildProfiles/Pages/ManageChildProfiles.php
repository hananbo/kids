<?php

namespace App\Filament\Parent\Resources\ChildProfiles\Pages;

use App\Filament\Parent\Resources\ChildProfiles\ChildProfileResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageChildProfiles extends ManageRecords
{
    protected static string $resource = ChildProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('פרופיל חדש')
                ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-test' => 'create-child-submit']))
                ->mutateDataUsing(fn (array $data): array => ['team_id' => Filament::auth()->user()?->current_team_id] + $data),
        ];
    }
}
