<?php

use App\Games\WordWheel\SpinWheel;
use App\Models\ChildProfile;
use App\Models\PublishedWordEntryVersion;
use App\Models\User;
use App\Models\WordEntry;

test('a child spins, reveals tiles, resumes, and solves a Hebrew puzzle', function () {
    $this->mock(SpinWheel::class)->shouldReceive('__invoke')->andReturn(3);
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    ChildProfile::factory()->for($team)->create(['learning_age' => 8]);
    PublishedWordEntryVersion::factory()->for(WordEntry::factory()->for($team)->create([
        'content' => [
            'word' => 'קשת בענן',
            'meaning' => 'פסים צבעוניים בשמיים אחרי גשם.',
            'hints' => [],
            'target_age' => 4,
            'subjects' => ['מזג אוויר'],
            'wheel_eligible' => true,
        ],
    ]))->create();

    $page = visit(route('login'))->resize(390, 844)
        ->type('email', $parent->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs("/{$team->slug}/dashboard")
        ->navigate('/parent/child-profiles')
        ->assertVisible('[data-test="select-child-hebrew-quiz"]')
        ->click('[data-test="select-child-word-wheel"]')
        ->assertPathIs("/{$team->slug}/word-wheel")
        ->assertSee('גלגל המילים')
        ->click('[data-test="wheel-start"]')
        ->assertPresent('[data-test="word-wheel"][data-stage="spin"]')
        ->assertSee('פסים צבעוניים בשמיים אחרי גשם.')
        ->assertDontSee('קשת בענן')
        ->assertVisible('[data-test="spin-wheel"] [data-graphics="webgl2"] canvas');

    foreach ([[390, 844], [1024, 600], [1440, 900]] as [$width, $height]) {
        $page->resize($width, $height);
        expect($page->script('document.documentElement.scrollWidth'))->toBeLessThanOrEqual($width);
        $page->assertVisible('[data-test="wheel-board"]')->assertVisible('[data-test="wheel-spin"]');
        expect($page->script(<<<'JS'
            () => {
                const spin = document.querySelector('[data-test="wheel-spin"]').getBoundingClientRect();

                return spin.bottom <= window.innerHeight && spin.height >= 44;
            }
            JS))->toBeTrue("The spin button is in view at {$width}×{$height}.");
    }

    $page->resize(390, 844);
    $page->click('[data-test="wheel-spin"]')
        ->assertAttribute('[data-test="word-wheel"]', 'data-spins', 1)
        ->assertPresent('[data-test="spin-wheel"][data-wheel-segment="3"]')
        ->assertPresent('[data-test="word-wheel"][data-stage="guess"]')
        ->click('[data-test="wheel-letter-ק"]')
        ->assertSeeIn('[data-test="wheel-board"] [data-revealed=true]', 'ק')
        ->assertSeeIn('[data-test="wheel-score"]', '300')
        ->refresh()
        ->assertSeeIn('[data-test="wheel-board"] [data-revealed=true]', 'ק')
        ->assertDontSee('קשת בענן')
        ->assertSeeIn('[data-test="wheel-score"]', '300')
        ->click('[data-test="wheel-open-solve"]')
        ->fill('[data-test="wheel-solution"]', 'קשת בענן')
        ->click('[data-test="wheel-solve"]')
        ->assertPresent('[data-test="word-wheel"][data-stage="solved"]')
        ->assertSee('קשת בענן')
        ->click('[data-test="wheel-replay"]')
        ->assertPresent('[data-test="word-wheel"][data-stage="spin"]')
        ->assertAttribute('[data-test="word-wheel"]', 'data-spins', 0)
        ->assertNotPresent('[data-test="spin-wheel"][data-wheel-segment]')
        ->assertVisible('[data-test="spin-wheel"] [data-graphics="webgl2"] canvas')
        ->assertNoJavaScriptErrors();
});
