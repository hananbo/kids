<?php

namespace App\Filament\Schemas;

use App\Enums\QuestionFieldType;
use App\Models\Question;
use App\Support\Catalog\QuestionMedia;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class QuestionSchema
{
    /**
     * @param  (Closure(Model, string): ?string)|null  $mediaUrl
     * @return list<Component>
     */
    public static function fields(bool $readOnly = false, ?Closure $mediaUrl = null): array
    {
        $labels = [
            'prompt' => 'שאלה',
            'correct_answer' => 'תשובה נכונה',
            'distractor_one' => 'תשובה שגויה ראשונה',
            'distractor_two' => 'תשובה שגויה שנייה',
            'distractor_three' => 'תשובה שגויה שלישית',
        ];

        $fields = [];
        foreach ($labels as $name => $label) {
            $fields[] = self::field($name, $label, $readOnly, $mediaUrl);
        }

        return [
            ...$fields,
            Textarea::make('content.explanation')
                ->label('הסבר')->required()->maxLength(2000)->disabled($readOnly)
                ->extraInputAttributes(['data-test' => 'draft-explanation']),
            Textarea::make('content.hint')
                ->label('רמז')->maxLength(1000)->disabled($readOnly)
                ->extraInputAttributes(['data-test' => 'draft-hint']),
            Repeater::make('content.sources')
                ->label('מקורות')
                ->simple(TextInput::make('url')
                    ->url()
                    ->rule('url:http,https')
                    ->required()
                    ->maxLength(2048)
                    ->extraInputAttributes(['data-test' => 'draft-source'])
                    ->suffixAction(Action::make('openSource')
                        ->label('פתיחת מקור')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (?string $state): ?string => $state && filter_var($state, FILTER_VALIDATE_URL) && in_array(parse_url($state, PHP_URL_SCHEME), ['http', 'https'], true) ? $state : null)
                        ->openUrlInNewTab()))
                ->maxItems(10)
                ->defaultItems(0)
                ->disabled($readOnly)
                ->addActionLabel('הוספת מקור'),
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

    /** @param (Closure(Model, string): ?string)|null $mediaUrl */
    private static function field(string $name, string $label, bool $readOnly, ?Closure $mediaUrl): Fieldset
    {
        $input = str_replace('_', '-', $name);
        $type = fn (Get $get): ?QuestionFieldType => QuestionFieldType::tryFrom((string) $get("content.$name.type"));
        $isMedia = fn (Get $get): bool => $type($get)?->isMedia() ?? false;

        return Fieldset::make($label)->columns(1)->schema([
            Select::make("content.$name.type")
                ->label('סוג תוכן')
                ->options(collect(QuestionFieldType::cases())->mapWithKeys(fn (QuestionFieldType $field): array => [$field->value => $field->label()])->all())
                ->default(QuestionFieldType::Text->value)
                ->live()
                ->required()
                ->disabled($readOnly)
                ->extraInputAttributes(['data-test' => "draft-$input-type"]),
            TextInput::make("content.$name.value")
                ->label($label)
                ->visible(fn (Get $get): bool => ! $isMedia($get))
                ->numeric(fn (Get $get): bool => $type($get) === QuestionFieldType::Number)
                ->required()->maxLength(255)->disabled($readOnly)
                ->extraInputAttributes(['data-test' => "draft-$input"]),
            FileUpload::make("content.$name.path")
                ->label('קובץ מדיה')
                ->visible($isMedia)
                ->disk(QuestionMedia::DISK)
                ->directory(fn (): string => (string) Filament::auth()->user()?->current_team_id)
                ->visibility('private')
                ->acceptedFileTypes(fn (Get $get): array => $type($get)?->mimeTypes() ?? [])
                ->maxSize(12288)
                ->preventFilePathTampering()
                ->getUploadedFileUsing(function (string $file, ?Model $record) use ($name, $mediaUrl): ?array {
                    $url = $record && $mediaUrl
                        ? $mediaUrl($record, $name)
                        : ($record instanceof Question ? route('parent.questions.media', ['question' => $record, 'field' => $name]) : null);

                    if (! $url) {
                        return null;
                    }

                    $disk = Storage::disk(QuestionMedia::DISK);

                    return ['name' => basename($file), 'size' => $disk->size($file), 'type' => $disk->mimeType($file) ?: null, 'url' => $url];
                })
                ->previewable(false)
                ->openable()
                ->required()->disabled($readOnly)
                ->extraAttributes(['data-test' => "draft-$input-upload"]),
            TextInput::make("content.$name.alternative")
                ->label('חלופה כתובה')
                ->visible($isMedia)
                ->required()->maxLength(500)->disabled($readOnly)
                ->extraInputAttributes(['data-test' => "draft-$input-alternative"]),
        ]);
    }
}
