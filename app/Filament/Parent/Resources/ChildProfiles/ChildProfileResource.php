<?php

namespace App\Filament\Parent\Resources\ChildProfiles;

use App\Actions\ChildProfiles\SelectChildProfile;
use App\Filament\Parent\Resources\ChildProfiles\Pages\ManageChildProfiles;
use App\Models\ChildProfile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;

class ChildProfileResource extends Resource
{
    protected static ?string $model = ChildProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'פרופיל ילד';

    protected static ?string $pluralModelLabel = 'פרופילי ילדים';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', Filament::auth()->user()?->current_team_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('שם')->required()->maxLength(255)
                ->extraInputAttributes(['data-test' => 'child-name']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('name')->label('שם')])
            ->recordActions([
                Action::make('play')
                    ->label('משחק')
                    ->icon(Heroicon::OutlinedPlay)
                    ->extraAttributes(['data-test' => 'select-child'])
                    ->action(function (ChildProfile $record, SelectChildProfile $select): RedirectResponse {
                        $select($record, request()->session());

                        return redirect()->route('quiz.play.show', ['current_team' => $record->team]);
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageChildProfiles::route('/')];
    }
}
