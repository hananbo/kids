<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $parent = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->callWith(HebrewQuizSeeder::class, ['team' => $parent->currentTeam]);
        $this->callWith(WordEntrySeeder::class, ['team' => $parent->currentTeam]);
    }
}
