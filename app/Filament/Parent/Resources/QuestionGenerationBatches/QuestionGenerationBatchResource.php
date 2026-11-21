<?php

namespace App\Filament\Parent\Resources\QuestionGenerationBatches;

use App\Actions\Catalog\GenerateQuestionBatch;
use App\Filament\Parent\Resources\QuestionGenerationBatches\Pages\ManageQuestionGenerationBatches;
use App\Filament\Parent\Resources\Questions\QuestionResource;
use App\Models\QuestionGenerationBatch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class QuestionGenerationBatchResource extends Resource
{
    protected static ?string $model = QuestionGenerationBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $modelLabel = 'אצוות שאלות';

    protected static ?string $pluralModelLabel = 'יצירת שאלות עם AI';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('team_id', Filament::auth()->user()?->current_team_id)
            ->withCount(['questions as published_questions_count' => fn (Builder $questions): Builder => $questions->whereHas('publishedVersion')]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('topic')->label('נושא')->wrap(),
                TextColumn::make('question_count')->label('שאלות'),
                TextColumn::make('stage')->label('שלב')
                    ->formatStateUsing(fn (string $state, QuestionGenerationBatch $record): string => match (true) {
                        $record->published_questions_count >= $record->question_count => 'פורסמה',
                        $record->published_questions_count > 0 => 'בפרסום',
                        default => match ($state) {
                            'new' => 'חדש',
                            'assessment' => 'הערכה',
                            'enrichment' => 'העשרה',
                            'draft' => 'טיוטה',
                            default => $state,
                        },
                    })
                    ->description(function (QuestionGenerationBatch $record): string {
                        $published = "{$record->published_questions_count} מתוך {$record->question_count} פורסמו";

                        return $record->failed_at === null
                            ? $published
                            : $published.' · העיבוד נכשל. אפשר לנסות שוב או להשלים שאלות קיימות ידנית.';
                    }),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('נסו שוב')
                    ->extraAttributes(['data-test' => 'retry-question-batch'])
                    ->visible(fn (QuestionGenerationBatch $record): bool => $record->stage !== 'draft'
                        && ($record->failed_at !== null || $record->updated_at?->lt(now()->subMinutes(2))))
                    ->action(function (QuestionGenerationBatch $record, GenerateQuestionBatch $generate): void {
                        Gate::authorize('update', $record->team);
                        $generate->dispatchAfterCommit($record->id);
                    }),
                Action::make('reviewDrafts')
                    ->label('פתחו את רשימת השאלות')
                    ->visible(fn (QuestionGenerationBatch $record): bool => in_array($record->stage, ['enrichment', 'draft'], true))
                    ->url(fn (): string => QuestionResource::getUrl()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageQuestionGenerationBatches::route('/')];
    }
}
