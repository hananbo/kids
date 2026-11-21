<?php

namespace Database\Seeders;

use App\Games\GamePackages;
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

        if (isset(app(GamePackages::class)->all()['hebrew-quiz'])) {
            $this->callWith(HebrewQuizSeeder::class, ['team' => $parent->currentTeam]);
        }

        $this->callWith(WordEntrySeeder::class, ['team' => $parent->currentTeam]);

        if (isset(app(GamePackages::class)->all()['word-wheel'])) {
            $this->callWith(WordWheelSeeder::class, ['team' => $parent->currentTeam]);
        }
    }
}
