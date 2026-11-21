<?php

use App\Filament\Parent\Resources\Questions\Pages\ManageQuestions;
use App\Models\ChildProfile;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Models\QuizRun;
use App\Models\QuizRunQuestion;
use App\Models\User;
use App\Support\Catalog\QuestionContent;
use Database\Seeders\HebrewQuizSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

test('a revised question stays pinned to each run and withdrawal ends only affected active runs', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    $sibling = ChildProfile::factory()->for($team)->create();
    $previousPlayer = ChildProfile::factory()->for($team)->create();
    app(HebrewQuizSeeder::class)->run($team);
    $question = Question::factory()->for($team)->create();
    $oldVersion = PublishedQuestionVersion::factory()->for($question)->create();
    $revisedPrompt = 'איזו תשובה מתאימה?';
    $play = route('quiz.play.show', $team);
    $start = route('quiz.play.start', $team);
    $childMode = ['team_id' => $team->id, 'profile_id' => $child->id];
    $siblingMode = ['team_id' => $team->id, 'profile_id' => $sibling->id];

    $completed = QuizRun::factory()->for($previousPlayer, 'childProfile')->create(['completed_at' => now()]);
    QuizRunQuestion::factory()->for($completed)->for($oldVersion, 'version')->create([
        'answered_at' => now(),
        'is_correct' => true,
        'selected_choice' => 0,
    ]);

    $this->actingAs($parent);
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Livewire::test(ManageQuestions::class)
        ->callAction(TestAction::make('edit')->table($question), data: [
            'content' => QuestionContent::normalize([...$question->content, 'prompt' => $revisedPrompt]),
        ])->assertHasNoActionErrors();

    $this->withSession(['child_mode' => $childMode])->post($start)->assertRedirect($play);
    $active = $child->quizRuns()->latest('id')->firstOrFail();
    $firstQuestion = $active->questions()->firstOrFail();
    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('question.prompt.value', $oldVersion->content['prompt'])
        ->etc()
    );

    session()->forget('child_mode');
    Livewire::test(ManageQuestions::class)
        ->callAction(TestAction::make('publish')->table($question))
        ->assertNotified('השאלה פורסמה');

    $this->withSession(['child_mode' => $childMode])->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('run.id', $active->id)
        ->where('question.prompt.value', $oldVersion->content['prompt'])
        ->etc()
    );

    $this->withSession(['child_mode' => $siblingMode])->post($start)->assertRedirect($play);

    session()->forget('child_mode');
    Livewire::test(ManageQuestions::class)
        ->callAction(TestAction::make('withdraw')->table($question), data: ['version' => $oldVersion->id])
        ->assertNotified('הגרסה הוסרה מהמשחק');

    $this->withSession(['child_mode' => $siblingMode])->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('run.cancelled', false)
        ->where('question.prompt.value', $revisedPrompt)
        ->etc()
    );

    $this->withSession(['child_mode' => $childMode])->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('run.cancelled', true)
        ->where('run.completed', false)
        ->where('question', null)
        ->etc()
    );

    $this->post(route('quiz.play.answer', ['current_team' => $team, 'run' => $active->id]), [
        'question' => $firstQuestion->id,
        'choice' => 0,
    ])->assertRedirect($play);

    expect($firstQuestion->refresh()->answered_at)->toBeNull();
    expect($completed->refresh()->completed_at)->not->toBeNull();
    expect($completed->questions()->where('is_correct', true)->count())->toBe(1);

    $this->post($start)->assertRedirect($play);
    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('run.id', fn (int $runId): bool => $runId !== $active->id)
        ->where('run.cancelled', false)
        ->where('run.total', 15)
        ->etc()
    );
});

test('withdrawing a current version keeps its previous approvals out of new runs', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    app(HebrewQuizSeeder::class)->run($team);
    $question = Question::factory()->for($team)->create();
    $previousVersion = PublishedQuestionVersion::factory()->for($question)->create();
    $currentVersion = PublishedQuestionVersion::factory()->for($question)->create();

    $this->actingAs($parent);
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Livewire::test(ManageQuestions::class)
        ->callAction(TestAction::make('withdraw')->table($question), data: ['version' => $currentVersion->id])
        ->assertNotified('הגרסה הוסרה מהמשחק');

    $this->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $child->id]])
        ->post(route('quiz.play.start', $team))
        ->assertRedirect(route('quiz.play.show', $team));

    $pinnedVersions = $child->quizRuns()->sole()->questions()->pluck('published_question_version_id')->all();
    expect($pinnedVersions)->toHaveCount(15)->not->toContain($currentVersion->id, $previousVersion->id);
});
