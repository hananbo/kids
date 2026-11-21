<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class WordEntrySchema
{
    /** @return list<Field> */
    public static function fields(bool $readOnly = false, bool $includeWheelSettings = true): array
    {
        return [
            TextInput::make('content.word')->label('מילת יעד')->required()->maxLength(255)
                ->disabled($readOnly)->extraInputAttributes(['data-test' => 'word-entry-word']),
            Textarea::make('content.meaning')->label('משמעות')->required()->maxLength(2000)
                ->disabled($readOnly)->extraInputAttributes(['data-test' => 'word-entry-meaning']),
            TagsInput::make('content.hints')->label('רמזים')->rules(['array', 'max:10'])
                ->nestedRecursiveRules(['required', 'string', 'max:500'])->disabled($readOnly),
            ...($includeWheelSettings ? [
                Toggle::make('content.wheel_eligible')->label('מתאים לגלגל המילים')->default(true)->disabled($readOnly),
                TextInput::make('content.target_age')->label('גיל למידה מינימלי')->integer()
                    ->minValue(1)->maxValue(18)->disabled($readOnly),
                TagsInput::make('content.subjects')->label('נושאים למשחק')
                    ->nestedRecursiveRules(['required', 'string', 'max:60'])->disabled($readOnly),
            ] : []),
        ];
    }
}
