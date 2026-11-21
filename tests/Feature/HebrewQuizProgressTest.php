<?php

use App\Models\ChildProfile;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Models\QuizRun;
use App\Models\QuizRunQuestion;
use App\Models\User;
use Database\Seeders\HebrewQuizSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('only two consecutive completed runs scoring at least six stars unlock the next level up to level three', function (int $startingLevel, int $highestLevel) {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $profile = ChildProfile::factory()->for($team)->create(['starting_level' => $startingLevel]);
    $sibling = ChildProfile::factory()->for($team)->create();
    app(HebrewQuizSeeder::class)->run($team);

    $otherGame = QuizRun::factory()->for($profile)->create(['game' => 'other-game', 'unlocked_level' => 3, 'completed_at' => now()]);
    QuizRunQuestion::factory()->for($otherGame)->create(['is_correct' => true, 'answered_at' => now()]);

    $play = route('quiz.play.show', $team);
    $start = route('quiz.play.start', $team);
    $this->actingAs($parent)->withSession([
        'child_mode' => ['profile_id' => $profile->id, 'team_id' => $team->id],
    ]);

    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('progress.highestLevel', $startingLevel)
        ->where('progress.stars', 0)
        ->etc()
    );

    $totalStars = 0;
    foreach ([6, 5, 6, 6] as $index => $score) {
        $this->post($start)->assertRedirect($play)->assertSessionHasNoErrors();
        $run = $profile->quizRuns()->latest('id')->firstOrFail();
        $answer = route('quiz.play.answer', [$team, $run]);
        $advance = route('quiz.play.advance', [$team, $run]);

        foreach ($run->questions as $position => $question) {
            $correctChoice = array_search('correct_answer', $question->choice_order, true);
            $choice = $position < $score ? $correctChoice : ($correctChoice + 1) % 4;
            $this->post($answer, ['question' => $question->id, 'choice' => $choice])->assertRedirect($play);

            if ($position === $score) {
                $this->get($play)->assertInertia(fn (Assert $page) => $page
                    ->where('run.completed', false)
                    ->where('run.stars', $score)
                    ->where('progress.highestLevel', $startingLevel)
                    ->etc()
                );
            }

            $this->post($advance, ['question' => $question->id])->assertRedirect($play);
            if ($position === $score) {
                break;
            }
        }

        $totalStars += $score;
        $this->get($play)->assertInertia(fn (Assert $page) => $page
            ->where('run.completed', true)
            ->where('run.stars', $score)
            ->where('progress.highestLevel', $index === 3 ? $highestLevel : $startingLevel)
            ->where('progress.stars', $totalStars)
            ->etc()
        );
    }

    $profile->update(['subjects' => ['ידע כללי']]);
    $this->post($start)->assertRedirect($play)->assertSessionHasNoErrors();
    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('run.level', $highestLevel)
        ->where('progress.stars', 23)
        ->etc()
    );

    $this->withSession(['child_mode' => ['profile_id' => $sibling->id, 'team_id' => $team->id]])
        ->get($play)
        ->assertInertia(fn (Assert $page) => $page
            ->where('progress.highestLevel', 1)
            ->where('progress.stars', 0)
            ->etc()
        );
})->with([
    'level 1 unlocks level 2' => [1, 2],
    'level 2 unlocks level 3' => [2, 3],
    'level 3 stays at level 3' => [3, 3],
]);

test('a run reveals one approved hint and a correct first answer after a hint still earns one star', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $profile = ChildProfile::factory()->for($team)->create();
    $sibling = ChildProfile::factory()->for($team)->create();
    $siblingRun = QuizRun::factory()->for($sibling)->create();
    app(HebrewQuizSeeder::class)->run($team);

    foreach (['הרמז השני', 'הרמז הראשון'] as $hint) {
        $question = Question::factory()->for($team)->create();
        PublishedQuestionVersion::factory()->for($question)->create([
            'content' => [...$question->content, 'hint' => $hint],
        ]);
        $question->update(['content' => [...$question->content, 'hint' => 'רמז שטרם אושר']]);
    }

    $play = route('quiz.play.show', $team);
    $this->actingAs($parent)
        ->withSession(['child_mode' => ['profile_id' => $profile->id, 'team_id' => $team->id]])
        ->post(route('quiz.play.start', $team))
        ->assertRedirect($play)
        ->assertSessionHasNoErrors();

    $run = $profile->quizRuns()->sole();
    [$first, $second] = $run->questions()->limit(2)->get();
    $hintUrl = route('quiz.play.hint', [$team, $run]);
    $answer = route('quiz.play.answer', [$team, $run]);

    $this->post(route('quiz.play.hint', [$team, $siblingRun]), ['question' => $first->id])->assertNotFound();
    $this->post($hintUrl, ['question' => $second->id])->assertRedirect($play);
    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('question.hintAvailable', true)
        ->where('question.hint', null)
        ->etc()
    );

    $this->post($hintUrl, ['question' => $first->id])->assertRedirect($play);
    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('question.hintAvailable', false)
        ->where('question.hint', 'הרמז הראשון')
        ->etc()
    );

    $correctChoice = array_search('correct_answer', $first->choice_order, true);
    $this->post($answer, ['question' => $first->id, 'choice' => $correctChoice])->assertRedirect($play);
    $this->post($answer, ['question' => $first->id, 'choice' => ($correctChoice + 1) % 4])->assertRedirect($play);
    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('run.stars', 1)
        ->where('question.feedback.correct', true)
        ->where('question.hint', 'הרמז הראשון')
        ->etc()
    );

    $this->post(route('quiz.play.advance', [$team, $run]), ['question' => $first->id])->assertRedirect($play);
    $this->post($hintUrl, ['question' => $second->id])->assertRedirect($play);
    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('run.stars', 1)
        ->where('question.hintAvailable', false)
        ->where('question.hint', null)
        ->etc()
    );
});
