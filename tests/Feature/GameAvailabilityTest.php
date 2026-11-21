<?php

use App\Games\HebrewQuiz\SelectQuizQuestions;
use App\Models\ChildProfile;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\HebrewQuizSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('availability gates new starts at both scopes while active runs can finish', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    $otherParent = User::factory()->create();
    $otherTeam = $otherParent->currentTeam;
    $otherChild = ChildProfile::factory()->for($otherTeam)->create();

    app(HebrewQuizSeeder::class)->run($team);
    app(HebrewQuizSeeder::class)->run($otherTeam);

    $start = route('quiz.play.start', $team);
    $play = route('quiz.play.show', $team);
    $childMode = ['team_id' => $team->id, 'profile_id' => $child->id];

    $this->actingAs($parent)->get('/admin/games')->assertForbidden();
    $this->withSession(['child_mode' => $childMode])->post($start)->assertRedirect($play);
    $run = $child->quizRuns()->sole();
    $game = Game::query()->where('key', SelectQuizQuestions::GAME)->sole();
    $game->households()->attach($team, ['enabled' => false]);

    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('available', false)
        ->where('run.id', $run->id)
        ->where('run.completed', false)
        ->etc()
    );
    $this->post($start)->assertRedirect($play);
    expect($child->quizRuns()->sole()->id)->toBe($run->id);

    $this->actingAs($otherParent)
        ->withSession(['child_mode' => ['team_id' => $otherTeam->id, 'profile_id' => $otherChild->id]])
        ->get(route('quiz.play.show', $otherTeam))
        ->assertInertia(fn (Assert $page) => $page->where('available', true)->etc());

    $game->update(['enabled' => false]);
    $this->post(route('quiz.play.start', $otherTeam))->assertSessionHasErrors('game');
    expect($otherChild->quizRuns()->count())->toBe(0);

    $this->actingAs($parent)->withSession(['child_mode' => $childMode]);
    foreach ($run->questions as $question) {
        $choice = array_search('correct_answer', $question->choice_order, true);
        $this->post(route('quiz.play.answer', [$team, $run]), ['question' => $question->id, 'choice' => $choice])
            ->assertRedirect($play);
        $this->post(route('quiz.play.advance', [$team, $run]), ['question' => $question->id])
            ->assertRedirect($play);
    }

    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('available', false)
        ->where('run.completed', true)
        ->where('run.stars', 8)
        ->where('progress.stars', 8)
        ->etc()
    );
    $this->from($play)->post($start)->assertRedirect($play)->assertSessionHasErrors('game');

    $game->update(['enabled' => true]);
    $this->post($start)->assertSessionHasErrors('game');
    $game->households()->updateExistingPivot($team, ['enabled' => true]);
    $this->post($start)->assertRedirect($play)->assertSessionDoesntHaveErrors('game');

    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('available', true)
        ->where('run.completed', false)
        ->where('progress.stars', 8)
        ->etc()
    );

    expect($child->quizRuns()->count())->toBe(2)
        ->and($run->questions()->where('is_correct', true)->count())->toBe(8);
});
