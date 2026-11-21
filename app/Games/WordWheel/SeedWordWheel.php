<?php

namespace App\Games\WordWheel;

use App\Models\Team;
use Database\Seeders\WordWheelSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('word-wheel:seed {team : Slug of an existing household}')]
#[Description('Seed the Hebrew word wheel puzzles for an existing household')]
class SeedWordWheel extends Command
{
    public function handle(WordWheelSeeder $seeder): int
    {
        $team = Team::query()->where('slug', $this->argument('team'))->first();

        if (! $team) {
            $this->error('Household not found.');

            return self::FAILURE;
        }

        $seeder->run($team);

        return self::SUCCESS;
    }
}
