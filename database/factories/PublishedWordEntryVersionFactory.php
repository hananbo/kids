<?php

namespace Database\Factories;

use App\Models\PublishedWordEntryVersion;
use App\Models\WordEntry;
use App\Support\Catalog\CatalogLocale;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PublishedWordEntryVersion> */
class PublishedWordEntryVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'word_entry_id' => WordEntry::factory(),
            'locale' => CatalogLocale::BASE,
            'content' => fn (array $attributes): array => WordEntry::query()->whereKey($attributes['word_entry_id'])->firstOrFail()->content,
        ];
    }
}
