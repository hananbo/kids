<?php

use App\Ai\Agents\AssessHebrewQuestions;
use App\Ai\Agents\EnrichHebrewQuestions;
use App\Models\ChildProfile;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Models\QuestionGenerationBatch;
use App\Models\User;

test('AI questions enter the quiz only after a parent reviews and publishes them', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    ChildProfile::factory()->for($team)->create();
    foreach ([1 => 2, 2 => 3, 3 => 2] as $difficulty => $count) {
        PublishedQuestionVersion::factory()->count($count)->create([
            'question_id' => fn () => Question::factory()->for($team)->create()->id,
            'difficulty' => $difficulty,
        ]);
    }

    AssessHebrewQuestions::fake([['questions' => [assessedHebrewQuestion()]]])->preventStrayPrompts();
    EnrichHebrewQuestions::fake([['questions' => [enrichedHebrewQuestion()]]])->preventStrayPrompts();

    $page = visit(route('login'))->resize(390, 844)
        ->type('email', $parent->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs("/{$team->slug}/dashboard");

    $page->navigate('/parent/question-generation-batches')
        ->click('[data-test="request-question-batch"]')
        ->fill('[data-test="generation-topic"]', 'תופעות טבע')
        ->fill('[data-test="generation-count"]', '1')
        ->click('[data-test="submit-question-batch"]')
        ->assertSee('טיוטה');

    $page->navigate('/parent/child-profiles')
        ->click('[data-test="select-child-hebrew-quiz"]')
        ->click('[data-test="start-run"]')
        ->assertDontSee('מה נוצר כשמים קופאים?')
        ->assertPresent('[data-test="start-run"]');

    $page->navigate('/parent/questions')
        ->assertPathIs('/user/confirm-password')
        ->fill('password', 'password')
        ->click('[data-test="confirm-password-button"]')
        ->assertPathIs('/parent/child-profiles')
        ->navigate('/parent/questions')
        ->assertSee('מה נוצר כשמים קופאים?')
        ->click('tr:has-text("מה נוצר כשמים קופאים?") [data-test="review-question"]')
        ->assertSee('בהכנה לפרסום')
        ->assertSee('צילום של קרח נמס')
        ->assertPresent('a[href="https://example.com/water"]')
        ->assertValue('[data-test="draft-explanation"]', 'בטמפרטורה נמוכה מספיק מים הופכים לקרח.')
        ->click('[data-test="publish-question-submit"]')
        ->assertSee('השאלה פורסמה')
        ->assertNoJavaScriptErrors();

    $page->navigate('/parent/question-generation-batches')
        ->assertSee('פורסמה')
        ->assertSee('1 מתוך 1 פורסמו');

    $page->navigate('/parent/child-profiles')
        ->click('[data-test="select-child-hebrew-quiz"]')
        ->click('[data-test="start-run"]')
        ->click('[data-test="open-question"]')
        ->assertSee('מה נוצר כשמים קופאים?')
        ->assertDontSee('https://example.com/water')
        ->assertDontSee('צילום של קרח נמס')
        ->click('קרח')
        ->click('[data-test="lock-answer"]')
        ->assertSee('בטמפרטורה נמוכה מספיק מים הופכים לקרח.')
        ->assertNoJavaScriptErrors();
});

test('a failed enrichment can be finished manually or retried without regenerating the drafts', function () {
    $parent = User::factory()->create();
    QuestionGenerationBatch::factory()->create(['topic' => 'נושא של בית אחר']);

    AssessHebrewQuestions::fake([['questions' => [
        assessedHebrewQuestion(),
        array_replace(assessedHebrewQuestion(), ['prompt' => 'איזו צורה יש לפתית שלג?']),
    ]]])->preventStrayPrompts();
    EnrichHebrewQuestions::fake([fn () => throw new RuntimeException('Provider unavailable')])->preventStrayPrompts();

    $page = visit(route('login'))->resize(390, 844)
        ->type('email', $parent->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs("/{$parent->currentTeam->slug}/dashboard");

    $page->navigate('/parent/question-generation-batches')
        ->assertDontSee('נושא של בית אחר')
        ->click('[data-test="request-question-batch"]')
        ->fill('[data-test="generation-topic"]', 'קרח ושלג')
        ->fill('[data-test="generation-count"]', '2')
        ->click('[data-test="submit-question-batch"]')
        ->assertSee('העיבוד נכשל');

    $page->navigate('/parent/questions')
        ->click('tr:has-text("מה נוצר כשמים קופאים?") [data-test="edit-question"]')
        ->fill('[data-test="draft-explanation"]', 'כשמים קופאים הם הופכים לקרח.')
        ->click('[data-test="save-question"]')
        ->click('tr:has-text("מה נוצר כשמים קופאים?") [data-test="review-question"]')
        ->assertValue('[data-test="draft-explanation"]', 'כשמים קופאים הם הופכים לקרח.')
        ->click('[data-test="publish-question-submit"]')
        ->assertSee('השאלה פורסמה');

    EnrichHebrewQuestions::fake([['questions' => [
        enrichedHebrewQuestion(),
        array_replace(enrichedHebrewQuestion(), [
            'question_number' => 2,
            'explanation' => 'לפתיתי שלג יש לרוב שישה קודקודים.',
        ]),
    ]]])->preventStrayPrompts();

    $page->navigate('/parent/question-generation-batches')
        ->assertSee('בפרסום')
        ->assertSee('1 מתוך 2 פורסמו')
        ->click('[data-test="retry-question-batch"]')
        ->assertSee('בפרסום')
        ->assertDontSee('העיבוד נכשל')
        ->navigate('/parent/questions')
        ->click('tr:has-text("איזו צורה יש לפתית שלג?") [data-test="review-question"]')
        ->assertValue('[data-test="draft-explanation"]', 'לפתיתי שלג יש לרוב שישה קודקודים.')
        ->assertNoJavaScriptErrors();

    AssessHebrewQuestions::assertPromptedTimes(1);
    expect(Question::query()->where('team_id', $parent->currentTeam->id)->count())->toBe(2);
});

function assessedHebrewQuestion(): array
{
    return [
        'prompt' => 'מה נוצר כשמים קופאים?',
        'correct_answer' => 'קרח',
        'distractor_one' => 'מלח',
        'distractor_two' => 'אדים',
        'distractor_three' => 'חול',
        'subjects' => ['טבע'],
        'target_age' => 6,
        'difficulty' => 1,
        'quiz_eligible' => true,
        'sources' => ['https://example.com/water'],
    ];
}

function enrichedHebrewQuestion(): array
{
    return [
        'question_number' => 1,
        'hint' => 'אפשר להכין את זה במקפיא.',
        'explanation' => 'בטמפרטורה נמוכה מספיק מים הופכים לקרח.',
        'media_suggestion' => 'צילום של קרח נמס',
    ];
}
