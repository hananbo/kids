<?php

namespace App\Filament\Parent\Resources\SharedQuestions;

use App\Actions\Catalog\CopySharedQuestion;
use App\Actions\Catalog\RestoreSharedQuestion;
use App\Filament\Parent\Resources\Questions\QuestionResource;
use App\Filament\Parent\Resources\SharedQuestions\Pages\ListSharedQuestions;
use App\Models\Question;
use App\Models\Team;
use App\Models\User;
use App\Support\Catalog\QuestionContent;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;

class SharedQuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $slug = 'shared-questions';

    protected static ?string $pluralModelLabel = 'קטלוג שאלות משותף';

    public static function getEloquentQuery(): Builder
    {
        $teamId = Filament::auth()->user()?->current_team_id;

        return parent::getEloquentQuery()
            ->withWhereHas('sharedVersion')
            ->with(['replacements' => fn (Relation $replacements): Relation => $replacements
                ->where('team_id', $teamId)
                ->with('livePrivateVersions:id,question_id,locale')]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sharedVersion.content.prompt')->label('שאלה')->state(fn (Question $record): string => QuestionContent::label($record->sharedVersion?->content['prompt'] ?? null))->wrap(),
                TextColumn::make('sharedVersion.difficulty')->label('קושי'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('adapt')
                    ->label('פתיחת עותק פרטי')
                    ->action(function (Question $record, CopySharedQuestion $copy): RedirectResponse {
                        $replacement = $copy($record, self::household());

                        return redirect(QuestionResource::getUrl('index', [
                            'tableAction' => 'edit',
                            'tableActionRecord' => $replacement->id,
                        ], panel: 'parent'));
                    }),
                Action::make('restore')
                    ->label('החזרת השאלה המקורית')
                    ->visible(fn (Question $record): bool => self::restorableLocales($record) !== [])
                    ->requiresConfirmation()
                    ->schema([
                        Select::make('locale')->label('שפה')
                            ->options(fn (Question $record): array => self::restorableLocales($record))
                            ->default(fn (Question $record): ?string => array_key_first(self::restorableLocales($record)))
                            ->required(),
                    ])
                    ->successNotificationTitle('השאלה המקורית חזרה למאגר')
                    ->action(function (Question $record, array $data, Action $action, RestoreSharedQuestion $restore): void {
                        $restore($record, self::household(), $data['locale']);
                        $action->success();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSharedQuestions::route('/')];
    }

    /** @return array<string, string> */
    private static function restorableLocales(Question $source): array
    {
        $replacement = $source->replacements->first();

        $locales = array_diff(
            $replacement->suppressed_locales ?? [],
            $replacement?->livePrivateVersions->pluck('locale')->all() ?? [],
        );

        return array_combine($locales, $locales);
    }

    private static function household(): Team
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User && $user->currentTeam !== null, 403);

        return $user->currentTeam;
    }
}
