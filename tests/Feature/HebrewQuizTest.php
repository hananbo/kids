<?php

use App\Models\ChildProfile;
use App\Models\Question;
use App\Models\QuizRun;
use App\Models\User;
use Database\Seeders\HebrewQuizSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('verified household parents can manage their own quiz from a separate parent panel', function () {
    $parent = User::factory()->create();

    $panel = $this->actingAs($parent)
        ->get('/parent/child-profiles')
        ->assertOk();

    expect($panel->headers->get('Content-Security-Policy'))
        ->toContain("'unsafe-eval'", "'nonce-", "object-src 'none'");

    $this->get('/admin')->assertForbidden();
});

test('child mode requires fresh parent confirmation to recover parent access', function () {
    $parent = User::factory()->admin()->create();

    $this->actingAs($parent)
        ->withSession(['child_mode' => ['profile_id' => 1, 'team_id' => $parent->current_team_id]])
        ->get('/admin')
        ->assertRedirect(route('child-mode.exit'));

    $this->get('/settings/profile')->assertRedirect(route('child-mode.exit'));
    $this->patch(route('profile.update'), ['name' => 'Child mode'])
        ->assertRedirect(route('child-mode.exit'));
    $this->get(route('child-mode.exit'))->assertRedirect(route('password.confirm'));

    $this->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertRedirect(route('child-mode.exit'));

    $this->get(route('child-mode.exit'))->assertSessionMissing('child_mode');
    $this->get('/admin')->assertOk();
});

test('a child cannot access another household or use a different child profile from their session', function () {
    $parent = User::factory()->create();
    $other = User::factory()->create();
    $child = ChildProfile::factory()->for($parent->currentTeam)->create();

    $this->actingAs($parent)
        ->withSession(['child_mode' => ['profile_id' => $child->id, 'team_id' => $other->current_team_id]])
        ->get("/{$other->currentTeam->slug}/quiz")
        ->assertForbidden();

    $this->get("/{$parent->currentTeam->slug}/quiz")->assertForbidden();
});

test('a run pins eight approved questions and accepts only the first answer', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    $sibling = ChildProfile::factory()->for($team)->create();
    $siblingRun = QuizRun::factory()->for($sibling)->create();

    app(HebrewQuizSeeder::class)->run($team);
    $draft = Question::factory()->for($team)->create();
    $otherQuestion = Question::factory()->create();
    $otherQuestion->versions()->create([
        'locale' => 'he',
        'content' => $otherQuestion->content,
        'difficulty' => 1,
        'quiz_eligible' => true,
    ]);
    $play = route('quiz.play.show', $team);
    $this->actingAs($parent)
        ->withSession(['child_mode' => ['profile_id' => $child->id, 'team_id' => $team->id]])
        ->post(route('quiz.play.start', $team))
        ->assertRedirect($play);

    $run = $child->quizRuns()->sole();
    $questions = $run->questions()->with('version')->get();
    expect($questions->pluck('version.difficulty')->all())->toBe([1, 1, 1, 2, 2, 2, 3, 3])
        ->and($questions->pluck('version.question_id')->unique())->toHaveCount(8)
        ->and($questions->pluck('version.question_id')->all())->not->toContain($draft->id, $otherQuestion->id);

    $first = $questions->first();
    $page = $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->component('games/hebrew-quiz')
        ->where('run.total', 8)
        ->where('question.id', $first->id)
        ->has('question.choices', 4)
        ->where('question.feedback', null)
        ->missing('question.correct_answer')
        ->missing('auth')
        ->missing('teams')
        ->etc()
    );

    expect($page->headers->get('Content-Security-Policy'))->not->toContain("'unsafe-eval'");

    $answerUrl = route('quiz.play.answer', ['current_team' => $team, 'run' => $run->id]);
    $correctChoice = array_search('correct_answer', $first->choice_order, true);
    $this->post($answerUrl, ['question' => $first->id, 'choice' => $correctChoice])->assertRedirect($play);
    $this->post($answerUrl, ['question' => $first->id, 'choice' => ($correctChoice + 1) % 4])->assertRedirect($play);
    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('question.feedback.selected', $correctChoice)
        ->where('question.feedback.correct', true)
        ->where('question.feedback.explanation', $first->version->content['explanation'])
        ->etc()
    );

    $this->post(route('quiz.play.answer', ['current_team' => $team, 'run' => $siblingRun->id]), [
        'question' => $first->id,
        'choice' => $correctChoice,
    ])->assertNotFound();
});
