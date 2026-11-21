<?php

use App\Models\ChildProfile;
use App\Models\QuizRun;
use App\Models\QuizRunQuestion;
use App\Models\User;

test('a parent saves independent learning settings and sees missing groups in Filament', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $profile = ChildProfile::factory()->for($team)->create(['name' => 'נועה']);
    $sibling = ChildProfile::factory()->for($team)->create(['name' => 'אורי']);
    $run = QuizRun::factory()->for($profile)->create(['completed_at' => now()]);
    $versionQuestion = QuizRunQuestion::factory()->for($run)->create(['answered_at' => now(), 'is_correct' => true]);

    $page = visit(route('login'))->resize(390, 844)
        ->type('email', $parent->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs("/{$team->slug}/dashboard");

    $page->navigate('/parent/child-profiles')
        ->assertSee('נועה')
        ->click('tr:has-text("נועה") [data-test="edit-child"]')
        ->fill('[data-test="learning-age"]', '9')
        ->select('[data-test="starting-level"]', '3')
        ->fill('[data-test="child-subjects"]', 'טבע')
        ->keys('[data-test="child-subjects"]', 'Enter')
        ->click('[data-test="save-child"]')
        ->click('tr:has-text("נועה") [data-test="inspect-pool"]')
        ->assertSee('רמת קושי 8–9: 0 מתוך 3 שאלות מאושרות.')
        ->assertNoJavaScriptErrors();

    expect($profile->refresh())
        ->learning_age->toBe(9)
        ->starting_level->toBe(3)
        ->subjects->toBe(['טבע']);
    expect($sibling->refresh())
        ->learning_age->toBe(6)
        ->starting_level->toBe(1)
        ->subjects->toBeNull();
    expect($versionQuestion->refresh()->is_correct)->toBeTrue();
});
