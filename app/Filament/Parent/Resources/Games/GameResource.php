<?php

namespace App\Filament\Parent\Resources\Games;

use App\Actions\Games\SetGameAvailability;
use App\Filament\Parent\Resources\Games\Pages\ManageGames;
use App\Models\Game;
use App\Models\Team;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $modelLabel = 'משחק';

    protected static ?string $pluralModelLabel = 'משחקים';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists([
                'households as disabled_for_household' => fn (Builder $households): Builder => $households
                    ->whereKey(Filament::auth()->user()?->current_team_id)
                    ->where('game_team.enabled', false),
            ]))
            ->columns([
                TextColumn::make('name')->label('משחק'),
                TextColumn::make('enabled')->label('זמינות בפלטפורמה')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'זמין' : 'מושבת בפלטפורמה'),
                ToggleColumn::make('enabled_for_household')
                    ->label('זמין למשפחה')
                    ->rules(['required', 'boolean'])
                    ->state(fn (Game $record): bool => ! $record->getAttribute('disabled_for_household'))
                    ->disabled(fn (Game $record): bool => ! $record->enabled)
                    ->extraAttributes(['data-test' => 'household-game-toggle'])
                    ->updateStateUsing(function (Game $record, bool $state, SetGameAvailability $set): bool {
                        $household = Filament::auth()->user()?->currentTeam;
                        abort_unless($household instanceof Team, 403);

                        return $set($record, $state, $household);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGames::route('/')];
    }
}
