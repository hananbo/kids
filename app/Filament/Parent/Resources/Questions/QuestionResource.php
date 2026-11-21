<?php

namespace App\Filament\Parent\Resources\Questions;

use App\Actions\Catalog\PublishQuestion;
use App\Actions\Catalog\WithdrawQuestionVersion;
use App\Filament\Parent\Resources\Questions\Pages\ManageQuestions;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $modelLabel = 'שאלה';

    protected static ?string $pluralModelLabel = 'שאלות';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', Filament::auth()->user()?->current_team_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::draftFields());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists([
                'versions as has_withdrawable_versions' => fn (Builder $versions): Builder => $versions->whereNull('withdrawn_at'),
            ]))
            ->columns([
                TextColumn::make('content.prompt')->label('שאלה')->wrap(),
                TextColumn::make('difficulty')->label('קושי'),
                TextColumn::make('publishedVersion.id')->label('מצב')->formatStateUsing(fn (): string => 'פורסמה')->placeholder('טיוטה'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make()->iconButton(),
                Action::make('publish')
                    ->label('סקירה ופרסום')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->extraAttributes(['data-test' => 'review-question'])
                    ->modalHeading('אישור השאלה לפרסום')
                    ->modalDescription('בדקו את השאלה, התשובות וההסבר שיופיעו במשחק.')
                    ->fillForm(fn (Question $record): array => [
                        'revision' => $record->draftRevision(),
                        'content' => $record->content,
                        'difficulty' => $record->difficulty,
                        'target_age' => $record->target_age,
                        'subjects' => $record->subjects,
                        'quiz_eligible' => $record->quiz_eligible,
                    ])
                    ->schema([Hidden::make('revision'), ...self::draftFields(readOnly: true)])
                    ->modalSubmitAction(fn (Action $action): Action => $action->label('פרסום')->extraAttributes(['data-test' => 'publish-question-submit']))
                    ->successNotificationTitle('השאלה פורסמה')
                    ->action(function (Question $record, array $data, Action $action, PublishQuestion $publish): void {
                        try {
                            $publish($record, $data['revision']);
                        } catch (ValidationException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                            $action->halt();
                        }

                        $action->success();
                    }),
                Action::make('withdraw')
                    ->label('הסרת גרסה מהמשחק')
                    ->color('danger')
                    ->visible(fn (Question $record): bool => (bool) $record->getAttribute('has_withdrawable_versions'))
                    ->requiresConfirmation()
                    ->modalDescription('הסרת גרסה תפסיק חידונים פעילים שבהם היא מופיעה.')
                    ->schema([
                        Select::make('version')
                            ->label('גרסה שפורסמה')
                            ->options(fn (Question $record): array => $record->versions()
                                ->whereNull('withdrawn_at')
                                ->latest('id')
                                ->get()
                                ->mapWithKeys(fn (PublishedQuestionVersion $version): array => [
                                    $version->id => "#{$version->id} · {$version->locale} · {$version->created_at?->format('Y-m-d H:i')}",
                                ])
                                ->all())
                            ->required(),
                    ])
                    ->successNotificationTitle('הגרסה הוסרה מהמשחק')
                    ->action(function (Question $record, array $data, Action $action, WithdrawQuestionVersion $withdraw): void {
                        $withdraw($record, (int) $data['version']);
                        $action->success();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageQuestions::route('/')];
    }

    /** @return list<Field> */
    private static function draftFields(bool $readOnly = false): array
    {
        return [
            TextInput::make('content.prompt')
                ->label('שאלה')->required()->maxLength(255)->disabled($readOnly)
                ->extraInputAttributes(['data-test' => 'draft-prompt']),
            TextInput::make('content.correct_answer')
                ->label('תשובה נכונה')->required()->maxLength(255)->disabled($readOnly)
                ->extraInputAttributes(['data-test' => 'draft-correct-answer']),
            TextInput::make('content.distractor_one')
                ->label('תשובה שגויה ראשונה')->required()->maxLength(255)->disabled($readOnly)
                ->extraInputAttributes(['data-test' => 'draft-distractor-one']),
            TextInput::make('content.distractor_two')
                ->label('תשובה שגויה שנייה')->required()->maxLength(255)->disabled($readOnly)
                ->extraInputAttributes(['data-test' => 'draft-distractor-two']),
            TextInput::make('content.distractor_three')
                ->label('תשובה שגויה שלישית')->required()->maxLength(255)->disabled($readOnly)
                ->extraInputAttributes(['data-test' => 'draft-distractor-three']),
            Textarea::make('content.explanation')
                ->label('הסבר')->required()->maxLength(2000)->disabled($readOnly)
                ->extraInputAttributes(['data-test' => 'draft-explanation']),
            TagsInput::make('subjects')
                ->label('נושאים')->nestedRecursiveRules(['required', 'string', 'max:60'])->disabled($readOnly),
            TextInput::make('target_age')
                ->label('גיל יעד')->integer()->minValue(1)->maxValue(18)->default(6)->required()->disabled($readOnly),
            TextInput::make('difficulty')
                ->label('רמת קושי')->integer()->minValue(1)->maxValue(10)->default(1)->required()->disabled($readOnly),
            Toggle::make('quiz_eligible')
                ->label('מתאימה לחידון הניצוצות')->disabled($readOnly)
                ->extraAttributes(['data-test' => 'draft-quiz-eligible']),
        ];
    }
}
