<?php

namespace App\Support\Catalog;

use App\Enums\QuestionFieldType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuestionContent
{
    public const CHOICES = ['correct_answer', 'distractor_one', 'distractor_two', 'distractor_three'];

    public const FIELDS = ['prompt', ...self::CHOICES];

    /** @return array{type: string, value?: string, path?: string, alternative?: string} */
    public static function field(mixed $value): array
    {
        if (is_string($value)) {
            return ['type' => 'text', 'value' => $value];
        }

        if (is_int($value) || is_float($value)) {
            return ['type' => 'number', 'value' => (string) $value];
        }

        $value = is_array($value) ? $value : [];

        $text = $value['value'] ?? null;

        return ['type' => is_string($value['type'] ?? null) ? $value['type'] : 'text'] + array_filter([
            'value' => is_int($text) || is_float($text) ? (string) $text : $text,
            'path' => $value['path'] ?? null,
            'alternative' => $value['alternative'] ?? null,
        ], is_string(...));
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function normalize(array $content): array
    {
        foreach (self::FIELDS as $name) {
            $draft = $content[$name] ?? null;
            $content[$name] = self::field($draft);

            if (is_array($draft) && ($draft['shared'] ?? false) === true
                && QuestionFieldType::tryFrom($content[$name]['type'])?->isMedia()) {
                $content[$name]['shared'] = true;
            }
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    public static function normalizeOverrides(array $override): array
    {
        foreach (self::FIELDS as $name) {
            $value = $override[$name] ?? null;

            if (is_scalar($value)) {
                $override[$name] = self::field($value);
            }
        }

        return $override;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    public static function localized(array $base, array $override): array
    {
        $content = $override + ['sources' => $base['sources'] ?? []];

        foreach (self::FIELDS as $name) {
            $draft = $override[$name] ?? null;
            $inherits = $draft === null || (is_array($draft) && ($draft['type'] ?? 'inherit') === 'inherit');

            if (! $inherits) {
                continue;
            }

            $shared = self::sharedField($base[$name] ?? null);
            $content[$name] = $shared === null
                ? null
                : $shared + ['alternative' => $draft['alternative'] ?? null];
        }

        return self::normalize($content);
    }

    /** @return array{type: string, value?: string, path?: string}|null */
    public static function sharedField(mixed $value): ?array
    {
        $field = self::field($value);
        $type = QuestionFieldType::tryFrom($field['type']);

        if ($type === QuestionFieldType::Number) {
            return ['type' => $type->value, 'value' => $field['value'] ?? ''];
        }

        if ($type?->isMedia() && is_array($value) && ($value['shared'] ?? false) === true) {
            return ['type' => $type->value, 'path' => $field['path'] ?? ''];
        }

        return null;
    }

    public static function label(mixed $value): string
    {
        $field = self::field($value);

        return $field['value'] ?? $field['alternative'] ?? '';
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $approvedSource
     * @return array<string, mixed>
     */
    public static function validate(array $content, int $teamId, array $approvedSource = []): array
    {
        $content = self::normalize($content);
        $rules = [
            'explanation' => ['required', 'string', 'max:2000'],
            'hint' => ['nullable', 'string', 'max:1000'],
            'sources' => ['sometimes', 'array', 'list', 'max:10'],
            'sources.*' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];

        foreach (self::FIELDS as $name) {
            $type = QuestionFieldType::tryFrom($content[$name]['type']);
            $rules["{$name}.type"] = ['required', Rule::enum(QuestionFieldType::class)];

            if ($type?->isMedia()) {
                $rules["{$name}.path"] = ['required', 'string', 'max:250'];
                $rules["{$name}.alternative"] = ['required', 'string', 'max:500'];
            } else {
                $rules["{$name}.value"] = ['required', 'string', 'max:255', ...($type === QuestionFieldType::Number ? ['numeric'] : [])];
            }
        }

        $validated = Validator::make($content, $rules)->validate();
        $choices = [];

        foreach (self::FIELDS as $name) {
            $field = $validated[$name];
            $type = QuestionFieldType::from($field['type']);

            if ($type->isMedia()) {
                $approved = self::field($approvedSource[$name] ?? null);
                $reusesApprovedFile = $approved['type'] === $type->value && ($approved['path'] ?? null) === $field['path'];

                if ((! str_starts_with($field['path'], "{$teamId}/") && ! $reusesApprovedFile) || ! QuestionMedia::file($type, $field['path'])) {
                    throw ValidationException::withMessages([$name => 'קובץ המדיה אינו זמין או אינו בפורמט הנתמך.']);
                }
            }

            if ($name !== 'prompt') {
                $choices[] = isset($field['path']) ? 'file:'.$field['path'] : 'value:'.trim($field['value']);
            }
        }

        if (count(array_unique($choices)) !== count($choices)) {
            throw ValidationException::withMessages(['choices' => 'ארבע התשובות חייבות להיות שונות.']);
        }

        return $validated;
    }
}
