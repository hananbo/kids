<?php

namespace App\Support\Catalog;

class CatalogLocale
{
    public const BASE = 'he';

    public const MAX_LENGTH = 16;

    public const PATTERN = '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/D';

    public static function valid(string $locale): bool
    {
        return strlen($locale) <= self::MAX_LENGTH && preg_match(self::PATTERN, $locale) === 1;
    }
}
