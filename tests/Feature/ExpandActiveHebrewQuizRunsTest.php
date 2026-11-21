<?php

use App\Games\HebrewQuiz\PrizeLadder;
use App\Games\HebrewQuiz\RecordQuizAnswer;
use App\Models\ChildProfile;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Models\QuizRun;
use App\Models\QuizRunQuestion;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the backfill appends eligible questions only to active short runs with seven distinct candidates', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $profile = ChildProfile::factory()->for($team)->create(['learning_age' => 9, 'subjects' => ['טבע']]);
    $sibling = ChildProfile::factory()->for($team)->create(['subjects' => ['טבע']]);
    $completed = QuizRun::factory()->for($profile)->create(['completed_at' => now()]);
    $cancelled = QuizRun::factory()->for($profile)->create(['cancelled_at' => now()]);
    $active = QuizRun::factory()->for($profile)->create(['position' => 1]);
    $shortPool = QuizRun::factory()->for($sibling)->create();

    $publish = fn (array $attributes = []): PublishedQuestionVersion => PublishedQuestionVersion::factory()
        ->for(Question::factory()->for($team))
        ->create(['difficulty' => 3, 'subjects' => ['טבע'], ...$attributes]);

    $existing = collect(range(1, 8))->map(fn (): PublishedQuestionVersion => $publish());
    $eligible = collect(range(1, 6))->map(fn (): PublishedQuestionVersion => $publish())
        ->push($publish(['target_age' => 7]));
    foreach ([
        ['difficulty' => 2], ['difficulty' => 4], ['target_age' => 18],
        ['quiz_eligible' => false], ['subjects' => ['שפה']],
    ] as $ineligible) {
        $publish($ineligible);
    }

    $publish()->forceFill(['withdrawn_at' => now()])->save();
    PublishedQuestionVersion::factory()->for($existing->first()->question)
        ->create(['difficulty' => 3, 'subjects' => ['טבע']]);

    foreach ([$active, $completed, $cancelled, $shortPool] as $run) {
        foreach ($existing as $position => $version) {
            QuizRunQuestion::factory()->for($run)->create([
                'published_question_version_id' => $version->id,
                'position' => $position,
                'choice_order' => array_reverse(RecordQuizAnswer::CHOICES),
                'answered_at' => $position === 0 ? now() : null,
                'is_correct' => $position === 0 ? true : null,
                'selected_choice' => $position === 0 ? 3 : null,
            ]);
        }
    }

    $before = $active->questions()->get()->toArray();
    $migration = require database_path('migrations/2026_11_21_111037_expand_active_hebrew_quiz_runs.php');

    $migration->up();
    $migration->up();

    $pins = $active->questions()->get();
    expect($pins->take(8)->toArray())->toBe($before);
    expect($pins->slice(8)->pluck('position')->all())->toBe(range(8, 14));
    expect($pins->slice(8)->pluck('published_question_version_id')->sort()->values()->all())
        ->toBe($eligible->pluck('id')->all());
    expect($pins->slice(8)->whereNotNull('answered_at'))->toBeEmpty();
    foreach ([$completed, $cancelled, $shortPool] as $unmodified) {
        expect($unmodified->questions()->count())->toBe(8);
    }

    $this->actingAs($parent)
        ->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $profile->id]])
        ->get(route('quiz.play.show', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('run.id', $active->id)
            ->where('run.total', 15)
            ->where('run.position', 2)
            ->where('run.prizes', PrizeLadder::FULL)
            ->where('question.id', $pins[1]->id));

    $this->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $sibling->id]])
        ->get(route('quiz.play.show', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('run.id', $shortPool->id)
            ->where('run.prizes', PrizeLadder::SHORT));
});
