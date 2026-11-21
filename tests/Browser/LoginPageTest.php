<?php

use App\Models\ChildProfile;
use App\Models\User;
use Database\Seeders\HebrewQuizSeeder;

test('the login page renders in the browser without javascript errors', function () {
    visit(route('login'))
        ->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});

test('parents who sign in from the child profile list can launch the quiz', function () {
    $parent = User::factory()->create();
    ChildProfile::factory()->for($parent->currentTeam)->create();
    app(HebrewQuizSeeder::class)->run($parent->currentTeam);

    visit(route('filament.parent.resources.child-profiles.index'))
        ->assertPathIs('/login')
        ->type('email', $parent->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs('/parent/child-profiles')
        ->click('[data-test="select-child-hebrew-quiz"]')
        ->assertSee('חידון הניצוצות')
        ->assertNoJavaScriptErrors();
});
