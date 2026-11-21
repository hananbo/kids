<?php

namespace App\Filament\Resources\Games;

use App\Actions\Games\SetGameAvailability;
use App\Filament\Resources\Games\Pages\ManageGames;
use App\Models\Game;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** @extends resource<Game> */
class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->installed();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Game'),
                ToggleColumn::make('enabled')
                    ->label('Enabled on the platform')
                    ->rules(['required', 'boolean'])
                    ->extraAttributes(['data-test' => 'platform-game-toggle'])
                    ->disabled(fn (Game $record): bool => Gate::denies('update', $record))
                    ->updateStateUsing(fn (Game $record, bool $state, SetGameAvailability $set): bool => $set($record, $state)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGames::route('/')];
    }
}
