<?php

namespace App\Filament\Resources\CatalogWordEntryVersions;

use App\Actions\Catalog\ShareCatalogVersion;
use App\Filament\Resources\CatalogWordEntryVersions\Pages\ListCatalogWordEntryVersions;
use App\Filament\Schemas\WordEntrySchema;
use App\Models\PublishedWordEntryVersion;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class CatalogWordEntryVersionResource extends Resource
{
    protected static ?string $model = PublishedWordEntryVersion::class;

    protected static ?string $slug = 'catalog-word-entry-versions';

    protected static ?string $navigationLabel = 'Shared Word Entry approvals';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-shared-catalog');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNull('withdrawn_at')
            ->whereNull('shared_from_version_id')
            ->withExists('sharedCopy');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content.word')->label('Target word')->wrap(),
                TextColumn::make('wordEntry.team.name')->label('Household'),
                TextColumn::make('locale')->label('Language'),
                TextColumn::make('id')->label('Version'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('share')
                    ->label('Review for Shared Catalog')
                    ->visible(fn (PublishedWordEntryVersion $record): bool => ! $record->getAttribute('shared_copy_exists'))
                    ->modalHeading('Review the exact published Word Entry')
                    ->modalSubmitActionLabel('Approve for Shared Catalog')
                    ->fillForm(fn (PublishedWordEntryVersion $record): array => ['content' => $record->content])
                    ->schema(WordEntrySchema::fields(readOnly: true))
                    ->successNotificationTitle('Word Entry version shared')
                    ->action(function (PublishedWordEntryVersion $record, Action $action, ShareCatalogVersion $share): void {
                        $share($record);
                        $action->success();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCatalogWordEntryVersions::route('/')];
    }
}
