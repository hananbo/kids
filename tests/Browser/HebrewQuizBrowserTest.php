<?php

use App\Models\ChildProfile;
use App\Models\User;
use Database\Seeders\HebrewQuizSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadController;

test('parents publish Hebrew content in Filament and a child plays it on a phone', function () {
    $parent = User::factory()->create();
    app(HebrewQuizSeeder::class)->run($parent->currentTeam);
    Storage::fake('quiz_media');
    Storage::fake('tmp-for-tests');

    // Pest's HTTP driver keeps multipart upload names flat, while PHP normally nests files[].
    app()->bind(FileUploadController::class, fn () => new class extends FileUploadController
    {
        public function handle()
        {
            $files = request()->files;
            if ($files->has('files[]')) {
                $files->set('files', [$files->get('files[]')]);
            }

            return parent::handle();
        }
    });

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
        ->fill('[data-test="draft-prompt"]', 'מה התוצאה של 2 + 3?')
        ->select('[data-test="draft-correct-answer-type"]', 'number')
        ->fill('[data-test="draft-correct-answer"]', '5')
        ->select('[data-test="draft-distractor-one-type"]', 'audio')
        ->fill('[data-test="draft-distractor-one-alternative"]', 'טון קצר')
        ->attach('[data-test="draft-distractor-one-upload"] input[type="file"]', __DIR__.'/Fixtures/tone.wav')
        ->fill('[data-test="draft-distractor-two"]', 'שש')
        ->fill('[data-test="draft-distractor-three"]', 'שבע')
        ->fill('[data-test="draft-explanation"]', 'חמש היא התשובה.')
        ->fill('[data-test="draft-hint"]', 'התחילו לספור משתיים.')
        ->click('הוספת מקור')
        ->fill('[data-test="draft-source"]', 'https://example.com/reviewer-source')
        ->click('[data-test="draft-quiz-eligible"]')
        ->click('[data-test="create-question-submit"]')
        ->assertSee('מה התוצאה של 2 + 3?')
        ->click('tbody tr:first-child [data-test="review-question"]')
        ->assertValue('[data-test="draft-explanation"]', 'חמש היא התשובה.')
        ->assertValue('[data-test="draft-hint"]', 'התחילו לספור משתיים.')
        ->assertValue('[data-test="draft-source"]', 'https://example.com/reviewer-source')
        ->assertPresent('a[href="https://example.com/reviewer-source"]')
        ->click('[data-test="publish-question-submit"]')
        ->assertSee('השאלה פורסמה')
        ->assertNoJavaScriptErrors();

    $page->navigate('/parent/child-profiles')
        ->click('פרופיל חדש')
        ->fill('[data-test="child-name"]', 'נועה')
        ->click('[data-test="create-child-submit"]')
        ->assertSee('נועה')
        ->click('[data-test="select-child-hebrew-quiz"]')
        ->assertSee('חידון הניצוצות')
        ->assertPresent('main[dir="rtl"][lang="he"][data-stage="welcome"]')
        ->assertVisible('[data-test="exit-quiz"]')
        ->assertAttribute('[data-test="quiz-sound"]', 'aria-pressed', 'false')
        ->click('[data-test="quiz-sound"]')
        ->assertAttribute('[data-test="quiz-sound"]', 'aria-pressed', 'true')
        ->click('[data-test="quiz-sound"]')
        ->assertAttribute('[data-test="quiz-sound"]', 'aria-pressed', 'false')
        ->click('[data-test="start-run"]')
        ->assertSee('שאלה 1 מתוך 15')
        ->assertPresent('main[data-stage="level-break"]')
        ->assertPresent('[data-studio-lighting="intermission"]')
        ->assertSeeIn('.spark-ladder-step[aria-current="step"] .spark-ladder-value', '100')
        ->assertSeeIn('.spark-ladder-step:first-child .spark-ladder-value', '1,000,000')
        ->assertDontSee('מה התוצאה של 2 + 3?')
        ->click('[data-test="open-question"]')
        ->assertSee('מה התוצאה של 2 + 3?')
        ->assertPresent('main[data-stage="playing"]')
        ->assertPresent('[data-studio-lighting="question"]')
        ->assertSeeIn('h2 bdi[dir="ltr"]', '2 + 3')
        ->assertPresent('audio[controls][preload="none"]')
        ->assertSeeIn('audio + figcaption', 'טון קצר')
        ->assertDontSee('https://example.com/reviewer-source')
        ->assertDontSee('חמש היא התשובה.')
        ->assertDontSee('התחילו לספור משתיים.');

    foreach ([[390, 844, 1], [1440, 900, 2]] as [$width, $height, $columns]) {
        $page->resize($width, $height);

        expect($page->script(<<<'JS'
            () => {
                const viewportWidth = document.documentElement.clientWidth;
                const choices = [...document.querySelectorAll('[data-test="choose-answer"]')];
                const answerTiles = [...choices[0].closest('[role="group"]').children];

                return {
                    columns: new Set(answerTiles.map(tile => tile.offsetLeft)).size,
                    usableButtons: choices
                        .map(button => button.getBoundingClientRect())
                        .filter(({ left, right, width, height }) =>
                            left >= 0 && right <= viewportWidth && width >= 44 && height >= 44
                        ).length,
                };
            }
            JS))->toBe(
            ['columns' => $columns, 'usableButtons' => 4],
            "The {$width}px viewport has {$columns} answer columns and four usable 44px targets.",
        );

        expect($page->script('document.documentElement.scrollWidth'))
            ->toBeLessThanOrEqual($width, "The {$width}px quiz viewport has no horizontal overflow.");
    }

    $page->resize(390, 844)
        ->click('[data-test="reveal-hint"]')
        ->assertSee('התחילו לספור משתיים.')
        ->assertSee('0 כוכבים בסבב');

    expect($page->script('(async () => {
        const audio = document.querySelector("audio");
        await audio.play();
        return audio.duration > 0;
    })()'))->toBeTrue();

    $page->click('div:has(> figure audio) > [data-test="choose-answer"]')
        ->assertDontSee('חמש היא התשובה.')
        ->click('[data-test="lock-answer"]')
        ->assertSee('חמש היא התשובה.')
        ->assertSee('התשובה הנכונה')
        ->assertDisabled('[data-test="choose-answer"][data-state="incorrect"]')
        ->assertPresent('[data-studio-lighting="incorrect"]')
        ->assertDisabled('[data-test="choose-answer"][data-state="correct"]')
        ->refresh()
        ->assertSee('שאלה 1 מתוך 15')
        ->assertSee('מה התוצאה של 2 + 3?')
        ->assertSee('חמש היא התשובה.')
        ->assertEnabled('[data-test="advance"]')
        ->assertNoJavaScriptErrors();

    $child = ChildProfile::query()->whereBelongsTo($parent->currentTeam)->where('name', 'נועה')->sole();
    $this->assertDatabaseHas('quiz_runs', ['child_profile_id' => $child->id, 'game' => 'hebrew-quiz']);
    $this->assertDatabaseHas('quiz_run_questions', ['quiz_run_id' => $child->quizRuns()->sole()->id, 'is_correct' => false]);

    $page->click('[data-test="advance"]')
        ->assertSee('החידון הסתיים!')
        ->assertPresent('main[data-stage="complete"]')
        ->assertSeeIn('[data-test="final-payout"]', '0 ניצוצות')
        ->assertNoJavaScriptErrors();
    expect($child->quizRuns()->sole()->completed_at)->not->toBeNull();

    $page->navigate('/parent/questions')
        ->assertPathIs('/user/confirm-password')
        ->fill('password', 'password')
        ->click('[data-test="confirm-password-button"]')
        ->assertPathIs('/parent/child-profiles')
        ->assertNoJavaScriptErrors();
});

test('a child confirms answers, uses lifelines, and walks away with the earned prize', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    app(HebrewQuizSeeder::class)->run($team);

    $page = visit(route('login'))->resize(390, 844)
        ->type('email', $parent->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs("/{$team->slug}/dashboard")
        ->navigate('/parent/child-profiles')
        ->click('[data-test="select-child-hebrew-quiz"]')
        ->click('[data-test="start-run"]')
        ->assertSee('שאלה 1 מתוך 15')
        ->click('[data-test="open-question"]')
        ->assertDisabled('[data-test="lock-answer"]');

    $page->resize(1024, 600);
    expect($page->script(<<<'JS'
        () => ['.spark-ladder', '[data-test="lock-answer"]'].every(selector =>
            document.querySelector(selector).getBoundingClientRect().bottom <= innerHeight
        )
        JS))->toBeTrue('The prize ladder and lock button fit in the desktop viewport.');

    $page->resize(844, 390);
    expect($page->script('document.documentElement.scrollWidth'))->toBeLessThanOrEqual(844);
    $page->resize(390, 844);

    $run = $child->quizRuns()->sole();
    $correctSlot = array_search('correct_answer', $run->questions()->firstOrFail()->choice_order, true);

    $page->click("[data-test='choose-answer'] >> nth={$correctSlot}")
        ->assertPresent('[data-test="choose-answer"][aria-pressed="true"]')
        ->assertNotPresent('[data-test="advance"]')
        ->click('[data-test="lock-answer"]')
        ->assertSee('נכון!')
        ->assertPresent('[data-studio-lighting="correct"]')
        ->click('[data-test="advance"]')
        ->assertSee('שאלה 2 מתוך 15')
        ->assertPresent('main[data-stage="level-break"]')
        ->assertSeeIn('.spark-ladder-step[data-state="earned"]', '100')
        ->assertSeeIn('.spark-ladder-step[aria-current="step"]', '200')
        ->click('[data-test="open-question"]')
        ->click('[data-test="lifeline-fifty"]')
        ->assertPresent('[data-test="choose-answer"][data-state="eliminated"]:disabled')
        ->assertDisabled('[data-test="lifeline-fifty"]')
        ->click('[data-test="lifeline-audience"]')
        ->assertSee('תוצאות הצבעת הקהל המדומה')
        ->assertPresent('.spark-vote')
        ->assertDisabled('[data-test="lifeline-audience"]')
        ->click('[data-test="lifeline-advisor"]')
        ->assertSee('היועץ המדומה מציע לבחור בתשובה')
        ->assertDisabled('[data-test="lifeline-advisor"]')
        ->click('[data-test="walk-away"]')
        ->click('[data-test="cancel-walk"]')
        ->assertVisible('[data-test="lock-answer"]')
        ->click('[data-test="walk-away"]')
        ->click('[data-test="confirm-walk"]')
        ->assertSee('סיימתם עם הקופה!')
        ->assertSeeIn('[data-test="final-payout"]', '100 ניצוצות')
        ->refresh()
        ->assertSeeIn('[data-test="final-payout"]', '100 ניצוצות')
        ->click('[data-test="start-run"]')
        ->assertSee('שאלה 1 מתוך 15')
        ->assertNoJavaScriptErrors();

    expect($run->fresh()->outcome)->toBe('walked');
});
