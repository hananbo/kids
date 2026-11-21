<?php

namespace App\Filament\Parent\Resources\ChildProfiles;

use App\Actions\ChildProfiles\SelectChildProfile;
use App\Filament\Parent\Resources\ChildProfiles\Pages\ManageChildProfiles;
use App\Games\HebrewQuiz\SelectQuizQuestions;
use App\Models\ChildProfile;
use App\Models\Question;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
            TextInput::make('learning_age')->label('גיל למידה')->integer()->minValue(1)->maxValue(18)->default(6)->required()
                ->extraInputAttributes(['data-test' => 'learning-age']),
            Select::make('starting_level')->label('רמת התחלה')->options([1 => '1', 2 => '2', 3 => '3'])->default(1)->required()
                ->extraInputAttributes(['data-test' => 'starting-level']),
            TagsInput::make('subjects')->label('נושאים')
                ->helperText('כשהרשימה ריקה, אפשר לשחק בכל הנושאים.')
                ->suggestions(fn (): array => Question::query()
                    ->where('team_id', Filament::auth()->user()?->current_team_id)
                    ->pluck('subjects')->flatten()->filter()->unique()->sort()->values()->all())
                ->nestedRecursiveRules(['required', 'string', 'max:60'])
                ->extraInputAttributes(['data-test' => 'child-subjects']),
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
                EditAction::make()
                    ->extraAttributes(['data-test' => 'edit-child'])
                    ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-test' => 'save-child'])),
                Action::make('inspectPool')
                    ->label('מצב מאגר השאלות')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->extraAttributes(['data-test' => 'inspect-pool'])
                    ->modalHeading('מצב מאגר השאלות')
                    ->modalDescription(function (ChildProfile $record, SelectQuizQuestions $selectQuestions): string {
                        $shortages = $selectQuestions->poolShortages($record);

                        return $shortages === []
                            ? 'יש מספיק שאלות מאושרות לפתיחת משחק ברמת ההתחלה.'
                            : implode(' ', $shortages);
                    })
                    ->modalSubmitAction(false),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageChildProfiles::route('/')];
    }
}
