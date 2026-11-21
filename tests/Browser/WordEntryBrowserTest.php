<?php

use App\Models\User;

test('a parent authors and approves a Hebrew Word Entry from a phone', function () {
    $parent = User::factory()->create();

    visit(route('login'))->resize(390, 844)
        ->type('email', $parent->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs("/{$parent->currentTeam->slug}/dashboard")
        ->navigate('/parent/word-entries')
        ->assertPresent('html[lang="he"][dir="rtl"]')
        ->click('ערך מילוני חדש')
        ->fill('[data-test="word-entry-word"]', 'מַצְפֵּן')
        ->fill('[data-test="word-entry-meaning"]', 'מכשיר שמראה את כיוון הצפון.')
        ->click('[data-test="create-word-entry"]')
        ->assertSee('מַצְפֵּן')
        ->click('[data-test="review-word-entry"]')
        ->assertValue('[data-test="word-entry-meaning"]', 'מכשיר שמראה את כיוון הצפון.')
        ->click('[data-test="publish-word-entry"]')
        ->assertSee('הגרסה פורסמה')
        ->assertNoJavaScriptErrors();
});
