<?php

namespace Database\Factories;

use App\Models\ChildProfile;
use App\Models\PublishedWordEntryVersion;
use App\Models\WordEntry;
use App\Models\WordWheelAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WordWheelAttempt> */
class WordWheelAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'child_profile_id' => ChildProfile::factory(),
            'published_word_entry_version_id' => fn (array $attributes): Factory => PublishedWordEntryVersion::factory()
                ->for(WordEntry::factory()->for(ChildProfile::query()->whereKey($attributes['child_profile_id'])->firstOrFail()->team))
                ->state(['content' => ['word' => 'שלום עולם', 'meaning' => 'ברכת פתיחה']]),
        ];
    }
}
