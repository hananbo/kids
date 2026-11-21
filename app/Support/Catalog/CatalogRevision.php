<?php

namespace App\Support\Catalog;

class CatalogRevision
{
    /** @param array<array-key, mixed> $payload */
    public static function hash(array $payload): string
    {
        return hash('sha256', serialize(self::canonical($payload)));
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function canonical(array $values): array
    {
        if (! array_is_list($values)) {
            ksort($values, SORT_STRING);
        }

        return array_map(
            fn (mixed $value): mixed => is_array($value) ? self::canonical($value) : $value,
            $values,
        );
    }
}
