<?php

namespace App\Filament\Parent\Resources\SharedWordEntries;

use App\Filament\Parent\Resources\SharedWordEntries\Pages\ListSharedWordEntries;
use App\Filament\Schemas\WordEntrySchema;
use App\Models\PublishedWordEntryVersion;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** @extends resource<PublishedWordEntryVersion> */
class SharedWordEntryResource extends Resource
{
    protected static ?string $model = PublishedWordEntryVersion::class;

    protected static ?string $slug = 'shared-word-entries';

    protected static ?string $pluralModelLabel = 'קטלוג ערכים מילוניים משותף';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->current(shared: true);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content.word')->label('מילת יעד')->wrap(),
                TextColumn::make('content.meaning')->label('משמעות')->wrap(),
                TextColumn::make('locale')->label('שפה'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('view')
                    ->label('פרטי הערך')
                    ->fillForm(fn (PublishedWordEntryVersion $record): array => ['content' => $record->content])
                    ->schema(WordEntrySchema::fields(readOnly: true))
                    ->modalSubmitAction(false),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSharedWordEntries::route('/')];
    }
}
