<?php

namespace App\Filament\Parent\Resources\WordEntries;

use App\Actions\Catalog\SaveCatalogLocalization;
use App\Filament\Actions\ReviewCatalogPublication;
use App\Filament\Parent\Resources\WordEntries\Pages\ManageWordEntries;
use App\Filament\Schemas\WordEntrySchema;
use App\Models\WordEntry;
use App\Support\Catalog\CatalogLocale;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WordEntryResource extends Resource
{
    protected static ?string $model = WordEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'ערך מילוני';

    protected static ?string $pluralModelLabel = 'ערכים מילוניים';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', Filament::auth()->user()?->current_team_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(WordEntrySchema::fields());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content.word')->label('מילת יעד')->wrap(),
                TextColumn::make('publishedVersion.content.word')->label('גרסה עברית שפורסמה')->placeholder('טיוטה')->wrap(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make()->iconButton(),
                self::publishAction(localized: false),
                self::editLocalizationAction(),
                self::publishAction(localized: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageWordEntries::route('/')];
    }

    private static function editLocalizationAction(): Action
    {
        return Action::make('editLocale')
            ->label('עריכת תרגום')
            ->fillForm(function (WordEntry $record): array {
                $locale = array_key_first($record->localizations ?? []);

                return ['locale' => $locale, 'content' => $record->localizations[$locale] ?? []];
            })
            ->schema([
                TextInput::make('locale')->label('קוד שפה')->placeholder('en')
                    ->datalist(fn (WordEntry $record): array => array_keys($record->localizations ?? []))
                    ->maxLength(CatalogLocale::MAX_LENGTH)->regex(CatalogLocale::PATTERN)
                    ->required()->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, WordEntry $record, Set $set) => $set('content', $record->localizations[$state] ?? [])),
                ...WordEntrySchema::fields(includeWheelSettings: false),
            ])
            ->successNotificationTitle('טיוטת התרגום נשמרה')
            ->action(function (WordEntry $record, array $data, Action $action, SaveCatalogLocalization $save): void {
                $save($record, $data['locale'], $data['content']);
                $action->success();
            });
    }

    private static function publishAction(bool $localized): Action
    {
        return ReviewCatalogPublication::make($localized, WordEntrySchema::fields(readOnly: true, includeWheelSettings: ! $localized))
            ->extraAttributes(['data-test' => $localized ? 'review-word-entry-localization' : 'review-word-entry'])
            ->modalHeading('אישור הערך לפרסום')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('פרסום')->extraAttributes(['data-test' => 'publish-word-entry']))
            ->successNotificationTitle('הגרסה פורסמה');
    }
}
