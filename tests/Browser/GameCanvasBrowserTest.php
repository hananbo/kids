<?php

use App\Models\ChildProfile;
use App\Models\User;
use Database\Seeders\HebrewQuizSeeder;

test('the game canvas stays within its pixel budget and recovers from graphics context loss', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    ChildProfile::factory()->for($team)->create();
    app(HebrewQuizSeeder::class)->run($team);

    $page = visit(route('login'))->resize(1440, 900)
        ->type('email', $parent->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs("/{$team->slug}/dashboard")
        ->navigate('/parent/child-profiles')
        ->click('[data-test="select-child-hebrew-quiz"]')
        ->assertVisible('[data-graphics="webgl2"] canvas');

    $pixels = $page->script(<<<'JS'
        () => {
            const canvas = document.querySelector('[data-graphics="webgl2"] canvas');

            return canvas.width * canvas.height;
        }
        JS);

    expect($pixels)->toBeGreaterThan(1)->toBeLessThanOrEqual(1_250_000);

    $page->script(<<<'JS'
        () => {
            const canvas = document.querySelector('[data-graphics="webgl2"] canvas');
            window.quizLostContext = canvas.getContext('webgl2').getExtension('WEBGL_lose_context');
            window.quizLostContext.loseContext();
        }
        JS);

    $page->assertPresent('[data-graphics="fallback"] canvas[hidden]')
        ->click('[data-test="start-run"]')
        ->click('[data-test="open-question"]')
        ->assertVisible('[data-test="lock-answer"]');

    $page->script(<<<'JS'
        () => window.quizLostContext.restoreContext()
        JS);

    $page->assertVisible('[data-graphics="webgl2"] canvas')
        ->assertNoJavaScriptErrors();
});
