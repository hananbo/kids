<?php

use App\Games\HebrewQuiz\PrizeLadder;
use App\Models\ChildProfile;
use App\Models\User;
use Database\Seeders\HebrewQuizSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $parent = User::factory()->create();
    $this->team = $parent->currentTeam;
    $profile = ChildProfile::factory()->for($this->team)->create(['learning_age' => 9]);
    app(HebrewQuizSeeder::class)->run($this->team);
    $this->actingAs($parent)->withSession(['child_mode' => ['team_id' => $this->team->id, 'profile_id' => $profile->id]]);
    $this->post(route('quiz.play.start', $this->team))->assertSessionHasNoErrors();
    $this->run = $profile->quizRuns()->sole();
});

test('the server settles the prize only once on loss, walk away and a perfect run', function (int $cleared, string $finish, int $payout) {
    $questions = $this->run->questions;
    expect($questions)->toHaveCount(15);
    $action = fn (string $name): string => route('quiz.play.'.$name, [$this->team, $this->run]);

    foreach ($questions->take($cleared) as $question) {
        $correct = array_search('correct_answer', $question->choice_order, true);
        $this->post($action('answer'), ['question' => $question->id, 'choice' => $correct]);
        $this->post($action('advance'), ['question' => $question->id]);
    }

    if ($finish !== 'won') {
        $question = $questions[$cleared];
        if ($finish === 'lost') {
            $correct = array_search('correct_answer', $question->choice_order, true);
            $this->post($action('answer'), ['question' => $question->id, 'choice' => ($correct + 1) % 4]);
            $this->post($action('walk'), ['question' => $question->id]);
            $this->post($action('advance'), ['question' => $question->id]);
        } else {
            $this->post($action('walk'), ['question' => $questions[0]->id]);
            expect($this->run->refresh()->isActive())->toBeTrue();
            $this->post($action('walk'), ['question' => $question->id, 'payout' => 1000000]);
        }
    }

    $this->post($action('walk'), ['question' => $questions->last()->id]);
    $this->get(route('quiz.play.show', $this->team))->assertInertia(fn (Assert $page) => $page
        ->where('run.outcome', $finish)->where('run.payout', $payout)
        ->where('run.prizes', PrizeLadder::FULL)->where('run.completed', true)->where('question', null));
})->with([[0, 'lost', 0], [5, 'lost', 1000], [10, 'lost', 32000], [3, 'walked', 300], [15, 'won', 1000000]]);

test('lifelines retain the randomized result across refresh and reject eliminated and stale choices', function () {
    $url = route('quiz.play.lifeline', [$this->team, $this->run]);
    $show = route('quiz.play.show', $this->team);
    $question = $this->run->questions->first();
    $id = $question->id;
    $fifty = ['question' => $id, 'kind' => 'fifty'];
    $this->post($url, ['question' => $this->run->questions->last()->id, 'kind' => 'fifty']);
    expect($this->run->refresh()->lifelines)->toBeNull();
    $this->post($url, $fifty);
    $result = $this->run->refresh()->lifelines['fifty']['result'];
    $correct = array_search('correct_answer', $question->choice_order, true);
    expect($result)->toHaveCount(2)->not->toContain($correct);
    $this->post($url, $fifty);
    $this->post(route('quiz.play.answer', [$this->team, $this->run]), ['question' => $id, 'choice' => $result[0]]);
    expect($question->refresh()->answered_at)->toBeNull();
    $this->post($url, ['question' => $id, 'kind' => 'audience']);
    $votes = $this->run->refresh()->lifelines['audience']['result'];
    expect(array_sum($votes))->toBe(100)->and($votes[$result[0]])->toBe(0)->and($votes[$result[1]])->toBe(0);
    $this->post($url, ['question' => $id, 'kind' => 'advisor']);
    $suggestion = $this->run->refresh()->lifelines['advisor']['result'][0];
    expect($suggestion)->toBeIn(array_diff([0, 1, 2, 3], $result));
    $this->post($url, ['question' => $id, 'kind' => 'advisor']);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('question.lifelines.advisorUsed', true)->where('question.lifelines.advisorChoice', $suggestion)
        ->where('question.lifelines.eliminated', $result)->where('question.lifelines.votes', $votes)->where('question.feedback', null));
});
