<?php

namespace App\Games\WordWheel;

final class WheelPuzzle
{
    public static function text(string $value): string
    {
        $unpointed = preg_replace('/\p{M}+/u', '', $value) ?? '';

        return trim(preg_replace('/[\s\p{Z}\x{05BE}\-]+/u', ' ', $unpointed) ?? '');
    }

    public static function comparable(string $value): string
    {
        return strtr(self::text($value), ['ך' => 'כ', 'ם' => 'מ', 'ן' => 'נ', 'ף' => 'פ', 'ץ' => 'צ']);
    }

    /** @param array<string, mixed> $content */
    public static function clue(array $content, string $answer): ?string
    {
        $hints = is_array($content['hints'] ?? null) ? $content['hints'] : [];
        $pattern = '/(?<![א-ת])'.preg_quote(self::comparable($answer), '/').'(?![א-ת])/u';

        foreach ([$content['meaning'] ?? null, ...$hints] as $clue) {
            if (is_string($clue) && trim($clue) !== '' && ! preg_match($pattern, self::comparable($clue))) {
                return trim($clue);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $guessedLetters
     * @return list<list<string|null>>
     */
    public static function board(string $answer, array $guessedLetters, bool $reveal): array
    {
        return array_map(
            fn (string $word): array => array_map(
                fn (string $letter): ?string => $reveal || in_array(self::comparable($letter), $guessedLetters, true) ? $letter : null,
                mb_str_split($word),
            ),
            explode(' ', $answer),
        );
    }
}
