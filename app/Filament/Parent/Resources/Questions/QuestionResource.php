<?php

namespace App\Filament\Parent\Resources\Questions;

use App\Actions\Catalog\PublishQuestion;
use App\Actions\Catalog\WithdrawQuestionVersion;
use App\Filament\Parent\Resources\Questions\Pages\ManageQuestions;
use App\Filament\Schemas\QuestionSchema;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Support\Catalog\QuestionContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
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
        return $schema->components(QuestionSchema::fields());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists([
                'versions as has_withdrawable_versions' => fn (Builder $versions): Builder => $versions->whereNull('withdrawn_at'),
            ]))
            ->columns([
                TextColumn::make('content.prompt')->label('שאלה')->state(fn (Question $record): string => QuestionContent::label($record->content['prompt'] ?? null))->wrap(),
                TextColumn::make('difficulty')->label('קושי'),
                TextColumn::make('publishedVersion.id')->label('מצב')->formatStateUsing(fn (): string => 'פורסמה')->placeholder('טיוטה'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->mutateRecordDataUsing(fn (array $data): array => ['content' => QuestionContent::normalize($data['content'])] + $data),
                Action::make('publish')
                    ->label('סקירה ופרסום')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->extraAttributes(['data-test' => 'review-question'])
                    ->modalHeading('אישור השאלה לפרסום')
                    ->modalDescription('בדקו את השאלה, התשובות וההסבר שיופיעו במשחק.')
                    ->fillForm(fn (Question $record): array => [
                        'revision' => $record->draftRevision(),
                        'content' => QuestionContent::normalize($record->content),
                        'difficulty' => $record->difficulty,
                        'target_age' => $record->target_age,
                        'subjects' => $record->subjects,
                        'quiz_eligible' => $record->quiz_eligible,
                    ])
                    ->schema([Hidden::make('revision'), ...QuestionSchema::fields(readOnly: true)])
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
}
