<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\WordEntry;
use App\Support\Catalog\CatalogLocale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WordEntrySeeder extends Seeder
{
    public function run(Team $team): void
    {
        DB::transaction(function () use ($team): void {
            $entry = WordEntry::query()->create([
                'team_id' => $team->id,
                'content' => [
                    'word' => 'מַצְפֵּן',
                    'meaning' => 'מכשיר שמראה את כיוון הצפון.',
                    'hints' => ['מטיילים נעזרים בו כדי למצוא את הדרך.'],
                ],
            ]);

            $entry->versions()->create(['locale' => CatalogLocale::BASE, 'content' => $entry->content]);
        });
    }
}
