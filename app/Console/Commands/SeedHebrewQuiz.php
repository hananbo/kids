<?php

namespace App\Console\Commands;

use App\Models\Team;
use Database\Seeders\HebrewQuizSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('quiz:seed {team : Slug of an existing household}')]
#[Description('Seed the Hebrew quiz launch questions for an existing household')]
class SeedHebrewQuiz extends Command
{
    public function handle(HebrewQuizSeeder $seeder): int
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
