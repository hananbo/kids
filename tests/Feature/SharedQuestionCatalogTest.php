<?php

use App\Actions\Catalog\CopySharedQuestion;
use App\Actions\Catalog\PlayableQuestionVersions;
use App\Actions\Catalog\PublishCatalogVersion;
use App\Actions\Catalog\SaveCatalogLocalization;
use App\Actions\Catalog\WithdrawQuestionVersion;
use App\Filament\Parent\Resources\Questions\Pages\ManageQuestions;
use App\Filament\Parent\Resources\SharedQuestions\Pages\ListSharedQuestions;
use App\Filament\Resources\CatalogQuestionVersions\Pages\ListCatalogQuestionVersions;
use App\Models\ChildProfile;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Models\User;
use App\Support\Catalog\QuestionContent;
use Database\Seeders\HebrewQuizSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

test('a platform admin shares the exact approved version while a later private publication stays private', function () {
    $contributor = User::factory()->create();
    $parent = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    app(HebrewQuizSeeder::class)->run($team);
    $question = Question::factory()->for($contributor->currentTeam)->create();
    $sharedContent = [...$question->content, 'prompt' => 'שאלה שאושרה לקטלוג'];
    $sharedVersion = PublishedQuestionVersion::factory()->for($question)->create(['content' => $sharedContent]);
    $privateVersion = PublishedQuestionVersion::factory()->for($question)->create([
        'content' => [...$sharedContent, 'prompt' => 'שאלה פרטית למשפחה'],
    ]);

    $this->actingAs($parent)->get('/admin/catalog-question-versions')->assertForbidden();
    $this->get('/parent/shared-questions')->assertOk()->assertDontSee('שאלה שאושרה לקטלוג');
    $this->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $child->id]])
        ->post(route('quiz.play.start', $team))
        ->assertRedirect(route('quiz.play.show', $team));
    expect($child->quizRuns()->sole()->questions()->pluck('published_question_version_id')->all())
        ->not->toContain($sharedVersion->id, $privateVersion->id);

    session()->forget('child_mode');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ListCatalogQuestionVersions::class)
        ->mountAction(TestAction::make('share')->table($sharedVersion))
        ->assertSchemaStateSet(['content.prompt.value' => 'שאלה שאושרה לקטלוג'])
        ->callMountedAction()
        ->assertNotified('Question version shared');

    $otherChild = ChildProfile::factory()->for($team)->create();
    $this->actingAs($parent)->get('/parent/shared-questions')
        ->assertSee('שאלה שאושרה לקטלוג')
        ->assertDontSee('שאלה פרטית למשפחה');
    $this->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $otherChild->id]])
        ->post(route('quiz.play.start', $team))
        ->assertRedirect(route('quiz.play.show', $team));
    $this->get(route('quiz.play.show', $team))->assertInertia(fn (Assert $page) => $page
        ->where('question.prompt.value', 'שאלה שאושרה לקטלוג')
        ->etc()
    );
});

test('a household adaptation replaces its shared source until the parent explicitly restores it', function () {
    $contributor = User::factory()->create();
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    app(HebrewQuizSeeder::class)->run($team);
    app(HebrewQuizSeeder::class)->run($contributor->currentTeam);
    $source = Question::factory()->for($contributor->currentTeam)->create();
    $sharedPrompt = 'שאלה משותפת';
    $replacementPrompt = 'שאלה שהותאמה למשפחה';
    $originalVersion = PublishedQuestionVersion::factory()->for($source)->create([
        'content' => [...$source->content, 'prompt' => $sharedPrompt],
    ]);
    $shared = PublishedQuestionVersion::factory()->for($source)->create([
        'content' => $originalVersion->content,
        'shared_from_version_id' => $originalVersion->id,
    ]);

    $play = function (ChildProfile $child, string $prompt): void {
        $team = $child->team;
        $this->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $child->id]])
            ->post(route('quiz.play.start', $team))
            ->assertRedirect(route('quiz.play.show', $team));
        $this->get(route('quiz.play.show', $team))->assertInertia(fn (Assert $page) => $page
            ->where('question.prompt.value', $prompt)
            ->etc()
        );
        session()->forget('child_mode');
    };

    $this->actingAs($parent);
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    $adapt = Livewire::test(ListSharedQuestions::class)
        ->callAction(TestAction::make('adapt')->table($source));

    $replacement = Question::query()->whereBelongsTo($team)->where('copied_from_version_id', $shared->id)->sole();
    $adapt->assertRedirect("/parent/questions?tableAction=edit&tableActionRecord={$replacement->id}");
    $play(ChildProfile::factory()->for($team)->create(), $sharedPrompt);

    Livewire::test(ManageQuestions::class)
        ->callAction(TestAction::make('edit')->table($replacement), data: [
            'content' => QuestionContent::normalize([...$replacement->content, 'prompt' => $replacementPrompt]),
        ])->assertHasNoActionErrors();
    Livewire::test(ManageQuestions::class)
        ->callAction(TestAction::make('publish')->table($replacement))
        ->assertNotified('השאלה פורסמה');

    $player = ChildProfile::factory()->for($team)->create();
    $play($player, $replacementPrompt);
    $this->actingAs($contributor);
    $play(ChildProfile::factory()->for($contributor->currentTeam)->create(), $sharedPrompt);

    $this->actingAs($parent);
    $replacementVersion = $replacement->publishedVersion()->firstOrFail();
    Livewire::test(ManageQuestions::class)
        ->callAction(TestAction::make('withdraw')->table($replacement), data: ['version' => $replacementVersion->id])
        ->assertNotified('הגרסה הוסרה מהמשחק');

    $this->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $player->id]])
        ->post(route('quiz.play.start', $team))
        ->assertRedirect(route('quiz.play.show', $team));
    expect($player->quizRuns()->latest('id')->firstOrFail()->questions()->pluck('published_question_version_id')->all())
        ->not->toContain($shared->id, $replacementVersion->id);

    session()->forget('child_mode');
    Livewire::test(ListSharedQuestions::class)
        ->callAction(TestAction::make('restore')->table($source))
        ->assertNotified('השאלה המקורית חזרה למאגר');

    $play(ChildProfile::factory()->for($team)->create(), $sharedPrompt);
});

test('only platform withdrawal ends runs pinned to a Shared Catalog version', function () {
    $contributor = User::factory()->create();
    $parent = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    app(HebrewQuizSeeder::class)->run($team);
    $source = Question::factory()->for($contributor->currentTeam)->create();
    $private = PublishedQuestionVersion::factory()->for($source)->create();
    $shared = PublishedQuestionVersion::factory()->for($source)->create([
        'shared_from_version_id' => $private->id,
    ]);
    $childMode = ['team_id' => $team->id, 'profile_id' => $child->id];

    $this->actingAs($parent)->withSession(['child_mode' => $childMode])
        ->post(route('quiz.play.start', $team))
        ->assertRedirect(route('quiz.play.show', $team));
    expect($child->quizRuns()->sole()->questions()->pluck('published_question_version_id')->all())
        ->toContain($shared->id);

    session()->forget('child_mode');
    $this->actingAs($contributor);
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Livewire::test(ManageQuestions::class)
        ->callAction(TestAction::make('withdraw')->table($source), data: ['version' => $shared->id])
        ->assertHasActionErrors(['version']);
    Livewire::test(ManageQuestions::class)
        ->callAction(TestAction::make('withdraw')->table($source), data: ['version' => $private->id])
        ->assertNotified('הגרסה הוסרה מהמשחק');

    $this->actingAs($parent)->withSession(['child_mode' => $childMode])
        ->get(route('quiz.play.show', $team))
        ->assertInertia(fn (Assert $page) => $page->where('run.cancelled', false)->etc());

    session()->forget('child_mode');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ListCatalogQuestionVersions::class)
        ->callAction(TestAction::make('withdraw')->table($shared))
        ->assertNotified('Shared question version withdrawn');

    $this->actingAs($parent)->withSession(['child_mode' => $childMode])
        ->get(route('quiz.play.show', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('run.cancelled', true)
            ->where('question', null)
            ->etc()
        );
});

test('a private replacement suppresses and restores only its approved locale', function () {
    $contributor = User::factory()->create();
    $parent = User::factory()->create();
    $source = Question::factory()->for($contributor->currentTeam)->create();
    foreach (['he', 'en'] as $locale) {
        $private = PublishedQuestionVersion::factory()->for($source)->create(['locale' => $locale]);
        PublishedQuestionVersion::factory()->for($source)->create([
            'locale' => $locale,
            'shared_from_version_id' => $private->id,
        ]);
    }

    $team = $parent->currentTeam;
    $this->actingAs($parent);
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    $replacement = app(CopySharedQuestion::class)($source, $team);
    app(SaveCatalogLocalization::class)($replacement, 'en', [
        ...$replacement->content,
        'prompt' => 'Which color belongs here?',
    ]);
    $replacement->refresh();

    $publish = app(PublishCatalogVersion::class);
    $publish($replacement, $replacement->draftRevision('en'), 'en');
    $pool = fn (string $locale) => app(PlayableQuestionVersions::class)(
        $team->id, fn (Builder $query) => $query->where('quiz_eligible', true), $locale,
    )->sole();

    expect($pool('he')->question_id)->toBe($source->id)
        ->and($pool('en')->question_id)->toBe($replacement->id);

    $hebrewCopy = $publish($replacement, $replacement->draftRevision());
    expect($pool('he')->question_id)->toBe($replacement->id);
    app(WithdrawQuestionVersion::class)($replacement, $hebrewCopy->id);
    expect(app(PlayableQuestionVersions::class)(
        $team->id, fn (Builder $query) => $query->where('quiz_eligible', true),
    ))->toBeEmpty();

    Livewire::test(ListSharedQuestions::class)
        ->callAction(TestAction::make('restore')->table($source))
        ->assertNotified('השאלה המקורית חזרה למאגר');

    expect($pool('he')->question_id)->toBe($source->id)
        ->and($pool('en')->question_id)->toBe($replacement->id);
});
