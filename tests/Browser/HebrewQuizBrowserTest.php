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
        ->click('[data-test="select-child"]')
        ->assertSee('חידון הניצוצות')
        ->assertPresent('main[dir="rtl"]')
        ->click('[data-test="start-run"]')
        ->assertSee('מה התוצאה של 2 + 3?')
        ->assertSeeIn('h2 bdi[dir="ltr"]', '2 + 3')
        ->assertPresent('audio[controls][preload="none"]')
        ->assertSeeIn('audio + figcaption', 'טון קצר')
        ->assertDontSee('https://example.com/reviewer-source')
        ->assertDontSee('חמש היא התשובה.')
        ->assertDontSee('התחילו לספור משתיים.')
        ->click('[data-test="reveal-hint"]')
        ->assertSee('התחילו לספור משתיים.')
        ->assertSee('0 כוכבים בסבב');

    expect($page->script('(async () => {
        const audio = document.querySelector("audio");
        await audio.play();
        return audio.duration > 0;
    })()'))->toBeTrue();

    $page->click('div:has(> figure audio) > [data-test="choose-answer"]')
        ->assertSee('חמש היא התשובה.')
        ->assertSee('התשובה הנכונה')
        ->refresh()
        ->assertSee('שאלה 1 מתוך 8')
        ->assertSee('מה התוצאה של 2 + 3?')
        ->assertSee('חמש היא התשובה.')
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
