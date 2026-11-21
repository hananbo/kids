<?php

namespace App\Enums;

enum QuestionFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'טקסט',
            self::Number => 'מספר',
            self::Image => 'תמונה',
            self::Audio => 'שמע',
            self::Video => 'וידאו',
        };
    }

    public function isMedia(): bool
    {
        return $this !== self::Text && $this !== self::Number;
    }

    /** @return list<string> */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            self::Audio => ['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/mp4', 'audio/webm'],
            self::Video => ['video/mp4', 'video/webm', 'video/ogg'],
            default => [],
        };
    }
}
