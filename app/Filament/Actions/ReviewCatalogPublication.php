<?php

namespace App\Filament\Actions;

use App\Actions\Catalog\PublishCatalogVersion;
use App\Models\Question;
use App\Models\WordEntry;
use App\Support\Catalog\CatalogLocale;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ReviewCatalogPublication
{
    /**
     * @param  list<Component>  $fields
     * @param  list<string>  $metadata
     */
    public static function make(bool $localized, array $fields, array $metadata = []): Action
    {
        $localeSelect = Select::make('locale')
            ->label('שפה לפרסום')
            ->options(fn (Question|WordEntry $record): array => collect(array_keys($record->localizations ?? []))
                ->mapWithKeys(fn (string $locale): array => [$locale => $locale])->all())
            ->required()
            ->live()
            ->extraInputAttributes(['data-test' => 'review-locale'])
            ->afterStateUpdated(function (?string $state, Question|WordEntry $record, Set $set): void {
                $locale = $state ?? CatalogLocale::BASE;
                $set('content', $record->localizedDraft($locale));
                $set('revision', $record->draftRevision($locale));
            });

        $action = Action::make($localized ? 'publishLocale' : 'publish')
            ->label($localized ? 'סקירה ופרסום של תרגום' : 'סקירה ופרסום')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (Question|WordEntry $record): bool => ! $localized || ! empty($record->localizations))
            ->fillForm(function (Question|WordEntry $record) use ($localized, $metadata): array {
                $locale = ($localized ? array_key_first($record->localizations ?? []) : null) ?? CatalogLocale::BASE;

                return [
                    'locale' => $locale,
                    'revision' => $record->draftRevision($locale),
                    'content' => $record->localizedDraft($locale),
                    ...$record->only($metadata),
                ];
            })
            ->schema([...($localized ? [$localeSelect] : []), Hidden::make('revision'), ...$fields])
            ->action(function (Question|WordEntry $record, array $data, Action $action, PublishCatalogVersion $publish) use ($localized): void {
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
