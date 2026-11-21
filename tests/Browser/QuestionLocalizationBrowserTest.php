<?php

use App\Models\Question;
use App\Models\User;

test('a parent switches and approves the correct locale through Filament', function () {
    $parent = User::factory()->create();
    $question = Question::factory()->for($parent->currentTeam)->create();
    $question->forceFill(['localizations' => [
        'en' => [
            'prompt' => 'What is glowing?',
            'correct_answer' => 'The sun',
            'distractor_one' => 'A spoon',
            'distractor_two' => 'A stone',
            'distractor_three' => 'A shoe',
            'explanation' => 'The sun gives light.',
        ],
        'fr' => [
            'prompt' => 'Que fait le soleil ?',
            'correct_answer' => 'Il brille',
            'distractor_one' => 'Il vole',
            'distractor_two' => 'Il parle',
            'distractor_three' => 'Il chante',
            'explanation' => 'Le soleil donne de la lumière.',
        ],
    ]])->save();

    $page = visit(route('login'))->resize(390, 844)
        ->type('email', $parent->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs("/{$parent->currentTeam->slug}/dashboard");

    $page->navigate('/parent/questions')
        ->click('[data-test="edit-localization"]')
        ->assertValue('[data-test="locale-code"]', 'en')
        ->fill('[data-test="locale-code"]', 'fr')
        ->assertValue('[data-test="draft-explanation"]', 'Le soleil donne de la lumière.')
        ->fill('[data-test="draft-explanation"]', 'Le soleil éclaire la Terre.')
        ->click('[data-test="save-localization"]')
        ->assertSee('טיוטת התרגום נשמרה')
        ->click('[data-test="review-localization"]')
        ->assertValue('[data-test="draft-explanation"]', 'The sun gives light.')
        ->select('[data-test="review-locale"]', 'fr')
        ->assertValue('[data-test="draft-explanation"]', 'Le soleil éclaire la Terre.')
        ->click('[data-test="publish-question-submit"]')
        ->assertSee('השאלה פורסמה')
        ->assertNoJavaScriptErrors();

    expect($question->versions()->sole()->locale)->toBe('fr');
});
