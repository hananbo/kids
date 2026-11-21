<?php

namespace App\Filament\Parent\Resources\Questions;

use App\Actions\Catalog\PublishQuestion;
use App\Actions\Catalog\SaveQuestionLocalization;
use App\Actions\Catalog\WithdrawQuestionVersion;
use App\Filament\Parent\Resources\Questions\Pages\ManageQuestions;
use App\Filament\Schemas\QuestionSchema;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Support\Catalog\CatalogLocale;
use App\Support\Catalog\QuestionContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
        return $schema->components([
            ...QuestionSchema::fields(),
            Textarea::make('generation_media_suggestion')
                ->label('הצעת מדיה מההעשרה')
                ->maxLength(1000)
                ->visible(fn (?Question $record): bool => $record?->question_generation_batch_id !== null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists([
                'versions as has_withdrawable_versions' => fn (Builder $versions): Builder => $versions
                    ->whereNull('withdrawn_at')
                    ->whereNull('shared_from_version_id'),
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
                    ->mutateRecordDataUsing(fn (array $data): array => ['content' => QuestionContent::normalize($data['content'])] + $data)
                    ->extraAttributes(['data-test' => 'edit-question'])
                    ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-test' => 'save-question'])),
                self::publishAction(),
                self::editLocalizationAction(),
                self::publishAction(localized: true),
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
                                ->whereNull('shared_from_version_id')
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

    private static function editLocalizationAction(): Action
    {
        return Action::make('editLocale')
            ->label('עריכת תרגום')
            ->extraAttributes(['data-test' => 'edit-localization'])
            ->modalHeading('טיוטה בשפה נוספת')
            ->fillForm(function (Question $record): array {
                $locale = array_key_first($record->localizations ?? []);

                return [
                    'locale' => $locale,
                    'content' => QuestionContent::normalizeOverrides($record->localizations[$locale ?? ''] ?? []),
                ];
            })
            ->schema([
                TextInput::make('locale')
                    ->label('קוד שפה')
                    ->placeholder('en')
                    ->datalist(fn (Question $record): array => array_keys($record->localizations ?? []))
                    ->maxLength(CatalogLocale::MAX_LENGTH)
                    ->regex(CatalogLocale::PATTERN)
                    ->required()
                    ->live(debounce: 250)
                    ->extraInputAttributes(['data-test' => 'locale-code'])
                    ->afterStateUpdated(fn (?string $state, Question $record, Set $set) => $set(
                        'content', QuestionContent::normalizeOverrides($record->localizations[$state ?? ''] ?? []),
                    )),
                Group::make(QuestionSchema::fields(localizationDraft: true))
                    ->visible(function (Get $get): bool {
                        $locale = $get('locale');

                        return is_string($locale) && $locale !== CatalogLocale::BASE && CatalogLocale::valid($locale);
                    }),
            ])
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['data-test' => 'save-localization']))
            ->successNotificationTitle('טיוטת התרגום נשמרה')
            ->action(function (Question $record, array $data, Action $action, SaveQuestionLocalization $save): void {
                $save($record, $data['locale'], $data['content'] ?? []);
                $action->success();
            });
    }

    private static function publishAction(bool $localized = false): Action
    {
        $localeSelect = Select::make('locale')
            ->label('שפה לפרסום')
            ->options(fn (Question $record): array => collect(array_keys($record->localizations ?? []))
                ->mapWithKeys(fn (string $locale): array => [$locale => $locale])->all())
            ->required()
            ->live()
            ->extraInputAttributes(['data-test' => 'review-locale'])
            ->afterStateUpdated(function (?string $state, Question $record, Set $set): void {
                $locale = $state ?? CatalogLocale::BASE;
                $set('content', $record->localizedDraft($locale));
                $set('revision', $record->draftRevision($locale));
            });

        $action = Action::make($localized ? 'publishLocale' : 'publish')
            ->label($localized ? 'סקירה ופרסום של תרגום' : 'סקירה ופרסום')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (Question $record): bool => ! $localized || ! empty($record->localizations))
            ->extraAttributes(['data-test' => $localized ? 'review-localization' : 'review-question'])
            ->modalHeading($localized ? 'אישור הגרסה לפרסום בשפה שנבחרה' : 'אישור השאלה לפרסום')
            ->modalDescription(fn (Question $record): string => $record->question_generation_batch_id === null
                ? 'בדקו את השאלה, התשובות וההסבר שיופיעו במשחק.'
                : 'בהכנה לפרסום. בדקו את השאלה, התשובות וההסבר שיופיעו במשחק.')
            ->fillForm(function (Question $record) use ($localized): array {
                $locale = ($localized ? array_key_first($record->localizations ?? []) : null) ?? CatalogLocale::BASE;

                return [
                    'locale' => $locale,
                    'revision' => $record->draftRevision($locale),
                    'content' => $record->localizedDraft($locale),
                    ...$record->only(['difficulty', 'target_age', 'subjects', 'quiz_eligible']),
                ];
            })
            ->schema([
                ...($localized ? [$localeSelect] : []),
                Hidden::make('revision'),
                ...QuestionSchema::fields(readOnly: true),
                TextEntry::make('generation_media_suggestion')
                    ->label('הצעת מדיה מההעשרה')
                    ->visible(fn (Question $record): bool => filled($record->generation_media_suggestion)),
            ])
            ->modalSubmitAction(fn (Action $action): Action => $action->label('פרסום')->extraAttributes(['data-test' => 'publish-question-submit']))
            ->successNotificationTitle('השאלה פורסמה')
            ->action(function (Question $record, array $data, Action $action, PublishQuestion $publish) use ($localized): void {
                try {
                    $publish($record, $data['revision'], $localized ? $data['locale'] : CatalogLocale::BASE);
                } catch (ValidationException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                    $action->halt();
                }

                $action->success();
            });

        return $localized ? $action : $action->iconButton();
    }
}
