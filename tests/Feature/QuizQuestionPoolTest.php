<?php

use App\Models\ChildProfile;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Models\QuizRun;
use App\Models\User;

test('quiz starts with eight distinct reviewed questions for the profile settings', function (int $level, array $difficulties) {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $profile = ChildProfile::factory()->for($team)->create([
        'learning_age' => 8,
        'starting_level' => $level,
        'subjects' => ['טבע'],
    ]);

    $expected = collect($difficulties)->map(function (int $difficulty) use ($team) {
        $question = Question::factory()->for($team)->create([
            'difficulty' => $difficulty,
            'target_age' => 8,
            'subjects' => ['מדעים', 'טבע'],
        ]);

        return PublishedQuestionVersion::factory()->for($question)->create()->id;
    });

    $firstQuestion = PublishedQuestionVersion::findOrFail($expected->first())->question;
    $firstQuestion->update(['subjects' => ['חשבון'], 'target_age' => 18]);

    foreach ([
        ['target_age' => 9, 'subjects' => ['טבע']],
        ['target_age' => 8, 'subjects' => ['חשבון']],
    ] as $metadata) {
        $question = Question::factory()->for($team)->create(['difficulty' => $difficulties[0], ...$metadata]);
        PublishedQuestionVersion::factory()->for($question)->create();
    }

    $this->actingAs($parent)
        ->withSession(['child_mode' => ['profile_id' => $profile->id, 'team_id' => $team->id]])
        ->post(route('quiz.play.start', $team))
        ->assertRedirect(route('quiz.play.show', $team))
        ->assertSessionHasNoErrors();

    $selected = $profile->quizRuns()->sole()->questions()->with('version')->get();
    expect($selected->pluck('version.difficulty')->all())->toBe($difficulties)
        ->and($selected->pluck('published_question_version_id')->sort()->values()->all())->toBe($expected->all());
})->with([
    'level 1' => [1, [1, 1, 1, 2, 2, 2, 3, 3]],
    'level 2' => [2, [4, 4, 4, 5, 5, 5, 6, 6]],
    'level 3' => [3, [7, 7, 7, 8, 9, 8, 10, 10]],
]);

test('quiz prefers unseen questions nearest the learning age before using the least recently seen question', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $profile = ChildProfile::factory()->for($team)->create(['learning_age' => 8]);

    $nearest = Question::factory()->for($team)->create(['target_age' => 8]);
    $younger = Question::factory()->for($team)->create(['target_age' => 7]);
    $older = Question::factory()->for($team)->create(['target_age' => 8]);
    $recent = Question::factory()->for($team)->create(['target_age' => 8]);
    foreach ([$nearest, $younger, $older, $recent] as $question) {
        PublishedQuestionVersion::factory()->for($question)->create();
    }

    $run = QuizRun::factory()->for($profile)->create(['position' => 1, 'completed_at' => now()]);
    foreach ([$older, $recent] as $position => $question) {
        $run->questions()->create([
            'published_question_version_id' => $question->publishedVersion->id,
            'position' => $position,
            'choice_order' => ['correct_answer', 'distractor_one', 'distractor_two', 'distractor_three'],
            'answered_at' => now()->subDays(2 - $position),
        ]);
    }

    PublishedQuestionVersion::factory()->for($recent)->create();
    foreach ([2, 2, 2, 3, 3] as $difficulty) {
        PublishedQuestionVersion::factory()->for(
            Question::factory()->for($team)->create(['difficulty' => $difficulty]),
        )->create();
    }

    $this->actingAs($parent)
        ->withSession(['child_mode' => ['profile_id' => $profile->id, 'team_id' => $team->id]])
        ->post(route('quiz.play.start', $team))
        ->assertRedirect(route('quiz.play.show', $team))
        ->assertSessionHasNoErrors();

    $group = $profile->quizRuns()->latest('id')->firstOrFail()->questions()->with('version')->limit(3)->get();
    expect($group->pluck('version.question_id')->all())->toBe([$nearest->id, $younger->id, $older->id]);
});

test('quiz cannot start when a reviewed difficulty group is short', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $profile = ChildProfile::factory()->for($team)->create(['starting_level' => 3]);
    foreach ([7, 7, 7, 8, 9, 10, 10] as $difficulty) {
        PublishedQuestionVersion::factory()->for(
            Question::factory()->for($team)->create(['difficulty' => $difficulty]),
        )->create();
    }

    $this->actingAs($parent)
        ->withSession(['child_mode' => ['profile_id' => $profile->id, 'team_id' => $team->id]])
        ->from(route('quiz.play.show', $team))
        ->post(route('quiz.play.start', $team))
        ->assertRedirect(route('quiz.play.show', $team))
        ->assertSessionHasErrors(['pool' => 'רמת קושי 8–9: 2 מתוך 3 שאלות מאושרות.']);

    expect($profile->quizRuns()->count())->toBe(0);
});

test('launch seed supplies two nonoverlapping runs at the configured starting level and learning age', function (int $level, int $age) {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $anotherHousehold = User::factory()->create()->currentTeam;
    $profile = ChildProfile::factory()->for($team)->create([
        'starting_level' => $level,
        'subjects' => ['ידע כללי'],
        'learning_age' => $age,
    ]);

    $this->artisan('quiz:seed', ['team' => $team->slug])->assertSuccessful();
    $this->artisan('quiz:seed', ['team' => $team->slug])->assertSuccessful();

    $this->actingAs($parent)
        ->withSession(['child_mode' => ['profile_id' => $profile->id, 'team_id' => $team->id]])
        ->post(route('quiz.play.start', $team))
        ->assertRedirect(route('quiz.play.show', $team))
        ->assertSessionHasNoErrors();

    $firstRun = $profile->quizRuns()->sole();
    foreach ($firstRun->questions as $runQuestion) {
        $this->post(route('quiz.play.answer', [$team, $firstRun]), ['question' => $runQuestion->id, 'choice' => 0])->assertSessionHasNoErrors();
        $this->post(route('quiz.play.advance', [$team, $firstRun]), ['question' => $runQuestion->id])->assertSessionHasNoErrors();
    }

    $this->post(route('quiz.play.start', $team))->assertSessionHasNoErrors();

    $secondRun = $profile->quizRuns()->latest('id')->firstOrFail();
    expect($secondRun->questions()->pluck('published_question_version_id')->intersect(
        $firstRun->questions()->pluck('published_question_version_id'),
    ))->toBeEmpty();
    expect($firstRun->questions()->with('version')->get()->pluck('version.target_age')->max())->toBe($age < 6 ? 1 : 6);
    expect(Question::query()->whereBelongsTo($team)->count())->toBe(96);
    expect(Question::query()->whereBelongsTo($anotherHousehold)->count())->toBe(0);
})->with([1, 2, 3])->with([1, 2, 3, 4, 5, 6, 18]);

test('launch seeding requires an existing household', function () {
    $this->artisan('quiz:seed', ['team' => 'missing-household'])
        ->expectsOutput('Household not found.')
        ->assertFailed();
});
