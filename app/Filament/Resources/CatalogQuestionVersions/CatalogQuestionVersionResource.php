<?php

namespace App\Filament\Resources\CatalogQuestionVersions;

use App\Actions\Catalog\ShareQuestionVersion;
use App\Actions\Catalog\WithdrawQuestionVersion;
use App\Filament\Parent\Resources\Questions\QuestionResource;
use App\Filament\Resources\CatalogQuestionVersions\Pages\ListCatalogQuestionVersions;
use App\Models\PublishedQuestionVersion;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class CatalogQuestionVersionResource extends Resource
{
    protected static ?string $model = PublishedQuestionVersion::class;

    protected static ?string $slug = 'catalog-question-versions';

    protected static ?string $navigationLabel = 'Shared Catalog approvals';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-shared-catalog');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('withdrawn_at')->withExists('sharedCopy');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content.prompt')->label('Question')->wrap(),
                TextColumn::make('question.team.name')->label('Household'),
                TextColumn::make('locale')->label('Language'),
                TextColumn::make('id')->label('Version'),
                TextColumn::make('shared_from_version_id')->label('Shared source')->placeholder('Household publication'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('share')
                    ->label('Review for Shared Catalog')
                    ->visible(fn (PublishedQuestionVersion $record): bool => $record->shared_from_version_id === null
                        && ! $record->getAttribute('shared_copy_exists'))
                    ->modalHeading('Review the exact published version')
                    ->modalSubmitActionLabel('Approve for Shared Catalog')
                    ->fillForm(fn (PublishedQuestionVersion $record): array => $record->only([
                        'content', 'difficulty', 'target_age', 'subjects', 'quiz_eligible',
                    ]))
                    ->schema(QuestionResource::draftFields(readOnly: true))
                    ->successNotificationTitle('Question version shared')
                    ->action(function (PublishedQuestionVersion $record, Action $action, ShareQuestionVersion $share): void {
                        $share($record);
                        $action->success();
                    }),
                Action::make('withdraw')
                    ->label('Withdraw from Shared Catalog')
                    ->color('danger')
                    ->visible(fn (PublishedQuestionVersion $record): bool => $record->shared_from_version_id !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Withdrawal ends active runs pinned to this Shared Catalog version.')
                    ->successNotificationTitle('Shared question version withdrawn')
                    ->action(function (PublishedQuestionVersion $record, Action $action, WithdrawQuestionVersion $withdraw): void {
                        $withdraw($record->question, $record->id);
                        $action->success();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCatalogQuestionVersions::route('/')];
    }
}
