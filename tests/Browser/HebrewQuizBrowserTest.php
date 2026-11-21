<?php

use App\Models\ChildProfile;
use App\Models\User;
use Database\Seeders\HebrewQuizSeeder;

test('parents publish Hebrew content in Filament and a child plays it on a phone', function () {
    $parent = User::factory()->create();
    app(HebrewQuizSeeder::class)->run($parent->currentTeam);

    $page = visit(route('login'))->resize(390, 844)
        ->type('email', $parent->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs("/{$parent->currentTeam->slug}/dashboard");

    $page->navigate('/settings/security')
        ->assertPathIs('/user/confirm-password')
        ->fill('password', 'password')
        ->click('[data-test="confirm-password-button"]')
        ->assertPathIs('/settings/security');

    $page->navigate("/{$parent->currentTeam->slug}/dashboard")
        ->click('[data-test="open-parent-panel"]')
        ->assertPathIs('/parent/child-profiles');

    $page->navigate('/parent/questions')
        ->assertPresent('html[lang="he"][dir="rtl"]')
        ->click('שאלה חדשה')
        ->fill('[data-test="draft-prompt"]', 'מה רואים ביום בשמיים?')
        ->fill('[data-test="draft-correct-answer"]', 'שמש')
        ->fill('[data-test="draft-distractor-one"]', 'לווייתן')
        ->fill('[data-test="draft-distractor-two"]', 'רכבת')
        ->fill('[data-test="draft-distractor-three"]', 'פרח')
        ->fill('[data-test="draft-explanation"]', 'השמש מאירה את היום.')
        ->click('[data-test="draft-quiz-eligible"]')
        ->click('[data-test="create-question-submit"]')
        ->assertSee('מה רואים ביום בשמיים?')
        ->click('tbody tr:first-child [data-test="review-question"]')
        ->assertValue('[data-test="draft-explanation"]', 'השמש מאירה את היום.')
        ->click('[data-test="publish-question-submit"]')
        ->assertSee('השאלה פורסמה')
        ->assertNoJavaScriptErrors();

    $page->navigate('/parent/child-profiles')
        ->click('פרופיל חדש')
        ->fill('[data-test="child-name"]', 'נועה')
        ->click('[data-test="create-child-submit"]')
        ->assertSee('נועה')
        ->click('[data-test="select-child"]')
        ->assertSee('חידון הניצוצות')
        ->assertPresent('main[dir="rtl"]')
        ->click('[data-test="start-run"]')
        ->assertSee('מה רואים ביום בשמיים?')
        ->assertDontSee('השמש מאירה את היום.')
        ->click('לווייתן')
        ->assertSee('השמש מאירה את היום.')
        ->assertSee('התשובה הנכונה')
        ->refresh()
        ->assertSee('שאלה 1 מתוך 8')
        ->assertSee('מה רואים ביום בשמיים?')
        ->assertSee('השמש מאירה את היום.')
        ->assertPresent('[data-test="advance"]')
        ->assertNoJavaScriptErrors();

    $child = ChildProfile::query()->whereBelongsTo($parent->currentTeam)->where('name', 'נועה')->sole();
    $this->assertDatabaseHas('quiz_runs', ['child_profile_id' => $child->id, 'game' => 'hebrew-quiz']);
    $this->assertDatabaseHas('quiz_run_questions', ['quiz_run_id' => $child->quizRuns()->sole()->id, 'is_correct' => false]);

    for ($position = 2; $position <= 8; $position++) {
        $page->click('[data-test="advance"]')
            ->assertSee("שאלה {$position} מתוך 8")
            ->click('[data-test="choose-answer"]:first-of-type')
            ->assertPresent('[data-test="advance"]');
    }

    $page->click('[data-test="advance"]')
        ->assertSee('החידון הסתיים!')
        ->assertNoJavaScriptErrors();
    expect($child->quizRuns()->sole()->completed_at)->not->toBeNull();

    $page->navigate('/parent/questions')
        ->assertPathIs('/user/confirm-password')
        ->fill('password', 'password')
        ->click('[data-test="confirm-password-button"]')
        ->assertPathIs('/parent/child-profiles')
        ->assertNoJavaScriptErrors();
});
