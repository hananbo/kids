<?php

use App\Models\ChildProfile;
use App\Models\User;
use Database\Seeders\HebrewQuizSeeder;

test('Filament switches control platform and household starts without clearing the household choice', function () {
    $admin = User::factory()->admin()->create();
    $household = $admin->currentTeam;
    ChildProfile::factory()->for($household)->create();
    app(HebrewQuizSeeder::class)->run($household);

    $platform = '[data-test="platform-game-toggle"] [role="switch"]';
    $householdToggle = '[data-test="household-game-toggle"] [role="switch"]';
    $page = visit(route('login'))->resize(390, 844)
        ->type('email', $admin->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs("/{$household->slug}/dashboard");

    $page->navigate('/parent/games')
        ->assertSee('חידון הניצוצות')
        ->assertPresent($householdToggle.'[aria-checked="true"]')
        ->click($householdToggle)
        ->assertPresent('[data-test="household-game-toggle"] [role="status"]:has-text("Change saved.")')
        ->assertPresent($householdToggle.'[aria-checked="false"]')
        ->navigate('/parent/child-profiles')
        ->click('[data-test="select-child"]')
        ->assertSee('החידון אינו זמין כרגע.')
        ->assertNotPresent('[data-test="start-run"]')
        ->assertNoJavaScriptErrors();

    $page->navigate('/parent/games')
        ->assertPathIs('/user/confirm-password')
        ->fill('password', 'password')
        ->click('[data-test="confirm-password-button"]')
        ->assertPathIs('/parent/child-profiles');

    $page->navigate('/admin/games')
        ->assertPresent($platform.'[aria-checked="true"]')
        ->click($platform)
        ->assertPresent('[data-test="platform-game-toggle"] [role="status"]:has-text("Change saved.")')
        ->assertPresent($platform.'[aria-checked="false"]')
        ->navigate('/parent/games')
        ->assertSee('מושבת בפלטפורמה')
        ->assertPresent($householdToggle.'[aria-disabled="true"]')
        ->assertNoJavaScriptErrors();

    $page->navigate('/admin/games')
        ->assertPresent($platform.'[aria-checked="false"]')
        ->click($platform)
        ->assertPresent('[data-test="platform-game-toggle"] [role="status"]:has-text("Change saved.")')
        ->assertPresent($platform.'[aria-checked="true"]')
        ->navigate('/parent/games')
        ->assertPresent($householdToggle.'[aria-checked="false"]')
        ->click($householdToggle)
        ->assertPresent('[data-test="household-game-toggle"] [role="status"]:has-text("Change saved.")')
        ->assertPresent($householdToggle.'[aria-checked="true"]')
        ->navigate('/parent/child-profiles')
        ->click('[data-test="select-child"]')
        ->click('[data-test="start-run"]')
        ->assertSee('שאלה 1 מתוך 8')
        ->assertNoJavaScriptErrors();
});
