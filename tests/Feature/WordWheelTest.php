<?php

use App\Filament\Parent\Resources\WordEntries\Pages\ManageWordEntries;
use App\Games\WordWheel\SpinWheel;
use App\Models\ChildProfile;
use App\Models\Game;
use App\Models\PublishedWordEntryVersion;
use App\Models\Team;
use App\Models\User;
use App\Models\WordEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

function publishWheelTestWord(Team $team, string $word, string $meaning, array $content = []): PublishedWordEntryVersion
{
    return PublishedWordEntryVersion::factory()
        ->for(WordEntry::factory()->for($team))
        ->create(['content' => ['word' => $word, 'meaning' => $meaning, ...$content]]);
}

test('word wheel scores every occurrence and applies loss segments and server move order', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    publishWheelTestWord($team, 'מַיִם וְשָׁמַיִם', 'שני דברים כחולים בתמונה של חוף');
    $this->mock(SpinWheel::class)->shouldReceive('__invoke')->times(5)->andReturn(1, 0, 5, 2, 0);

    $show = route('word-wheel.play.show', $team);
    $start = route('word-wheel.play.start', $team);
    $this->actingAs($parent)->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $child->id]])
        ->post($start)->assertRedirect($show);
    $attempt = $child->wordWheelAttempts()->sole();
    $move = fn (string $move): string => route("word-wheel.play.{$move}", [$team, $attempt]);

    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->component('games/word-wheel')
        ->where('attempt.board', [[null, null, null], [null, null, null, null, null]])
        ->where('attempt.solution', null)
        ->where('attempt.phase', 'spin')
        ->etc()
    );
    $this->from($show)->post($move('guess'), ['revision' => 0, 'letter' => 'מ'])
        ->assertSessionHasErrors('round');
    $this->post($move('guess'), ['revision' => 0, 'letter' => 'ab'])
        ->assertSessionHasErrors('letter');
    $this->post($move('spin'), ['revision' => 0, 'score' => 999, 'spinIndex' => 7])->assertRedirect($show);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('attempt.score', 0)
        ->where('attempt.spinIndex', 1)
        ->where('attempt.feedback', ['kind' => 'spin', 'points' => 200])
        ->etc()
    );
    $this->post($move('spin'), ['revision' => 1])->assertSessionHasErrors('round');
    $this->post($move('guess'), ['revision' => 0, 'letter' => 'ם'])->assertSessionHasErrors('round');
    $this->post($move('guess'), ['revision' => 1, 'letter' => 'ם'])->assertRedirect($show);
    $this->post($start)->assertRedirect($show);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('attempt.id', $attempt->id)
        ->where('attempt.board', [['מ', null, 'ם'], [null, null, 'מ', null, 'ם']])
        ->where('attempt.solution', null)
        ->where('attempt.score', 800)
        ->where('attempt.guessedLetters', ['מ'])
        ->where('attempt.feedback', ['kind' => 'hit', 'matches' => 4, 'points' => 800])
        ->etc()
    );

    $this->post($move('spin'), ['revision' => 2])->assertRedirect($show);
    $this->post($move('guess'), ['revision' => 3, 'letter' => 'מ'])->assertSessionHasErrors('letter');
    $this->post($move('guess'), ['revision' => 3, 'letter' => 'ב'])->assertRedirect($show);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('attempt.feedback', ['kind' => 'miss', 'matches' => 0, 'points' => 0])
        ->where('attempt.score', 800)
        ->etc()
    );
    $this->post($move('spin'), ['revision' => 4])->assertRedirect($show);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('attempt.spinIndex', 5)
        ->where('attempt.phase', 'spin')
        ->where('attempt.score', 800)
        ->where('attempt.feedback', ['kind' => 'lose-turn'])
        ->etc()
    );
    $this->post($move('spin'), ['revision' => 5])->assertRedirect($show);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('attempt.spinIndex', 2)
        ->where('attempt.phase', 'spin')
        ->where('attempt.score', 0)
        ->where('attempt.feedback', ['kind' => 'bankrupt'])
        ->etc()
    );
    $this->post($move('spin'), ['revision' => 6])->assertRedirect($show);
    $this->post($move('solve'), ['revision' => 7, 'solution' => 'תשובה שגויה'])->assertRedirect($show);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('attempt.phase', 'spin')
        ->where('attempt.feedback', ['kind' => 'incorrect'])
        ->where('attempt.solution', null)
        ->etc()
    );
    $this->post($move('solve'), ['revision' => 8, 'solution' => 'מימ-ושמימ'])->assertRedirect($show);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('attempt.phase', 'solved')
        ->where('attempt.solution', 'מַיִם וְשָׁמַיִם')
        ->where('progress.solved', 1)
        ->etc()
    );
    expect($child->wordWheelAttempts()->sole()->revision)->toBe(9);
});

test('word wheel completes by letters, records best score, and replays with a different puzzle', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    publishWheelTestWord($team, 'אבא', 'הורה במשפחה');
    $next = publishWheelTestWord($team, 'גשם', 'טיפות שיורדות מעננים');
    $this->mock(SpinWheel::class)->shouldReceive('__invoke')->twice()->andReturn(0);

    $show = route('word-wheel.play.show', $team);
    $start = route('word-wheel.play.start', $team);
    $this->actingAs($parent)->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $child->id]])
        ->post($start)->assertRedirect($show);
    $attempt = $child->wordWheelAttempts()->sole();

    foreach (['א', 'ב'] as $revision => $letter) {
        $this->post(route('word-wheel.play.spin', [$team, $attempt]), ['revision' => $revision * 2])->assertRedirect($show);
        $this->post(route('word-wheel.play.guess', [$team, $attempt]), ['revision' => $revision * 2 + 1, 'letter' => $letter])->assertRedirect($show);
    }

    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('attempt.phase', 'solved')
        ->where('attempt.solution', 'אבא')
        ->where('attempt.score', 300)
        ->where('progress', ['solved' => 1, 'bestScore' => 300])
        ->etc()
    );
    $this->post($start)->assertRedirect($show);
    $replay = $child->wordWheelAttempts()->latest('id')->firstOrFail();
    expect($replay->id)->not->toBe($attempt->id)
        ->and($replay->published_word_entry_version_id)->toBe($next->id);

    $this->post(route('word-wheel.play.finish', [$team, $attempt]), ['revision' => 4])->assertSessionHasErrors('round');
    $this->post(route('word-wheel.play.finish', [$team, $replay]), ['revision' => 0])->assertRedirect($show);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('attempt.phase', 'finished')
        ->where('attempt.solution', 'גשם')
        ->where('progress', ['solved' => 1, 'bestScore' => 300])
        ->etc()
    );
});

test('word wheel uses only current reviewed Hebrew puzzles allowed by the child age and subjects', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $contributor = User::factory()->create();
    $child = ChildProfile::factory()->for($team)->create(['learning_age' => 6, 'subjects' => ['טבע']]);
    $content = ['subjects' => ['טבע'], 'target_age' => 6];
    WordEntry::factory()->for($team)->create(['content' => ['word' => 'עץ', 'meaning' => 'צמח גבוה', ...$content]]);
    publishWheelTestWord($team, 'ענן', 'לבן בשמיים', ['subjects' => ['מזג אוויר']]);
    publishWheelTestWord($team, 'ציפור', 'בעלת כנפיים', [...$content, 'target_age' => 7]);
    publishWheelTestWord($team, 'שדה', 'מקום שבו צמחים גדלים', $content)->forceFill(['locale' => 'en'])->save();
    publishWheelTestWord($team, 'תפוח', 'תפוח גדל על העץ', $content);
    $old = publishWheelTestWord($team, 'חול', 'גרגרים קטנים', $content);
    PublishedWordEntryVersion::factory()->for($old->wordEntry)->create([
        'content' => [...$old->content, 'wheel_eligible' => false],
    ]);
    $external = publishWheelTestWord($contributor->currentTeam, 'גבעה', 'הר נמוך', $content);

    $show = route('word-wheel.play.show', $team);
    $start = route('word-wheel.play.start', $team);
    $this->actingAs($parent)->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $child->id]])
        ->get($show)->assertInertia(fn (Assert $page) => $page->where('poolAvailable', false)->where('attempt', null)->etc());
    $this->from($show)->post($start)->assertSessionHasErrors('pool');

    $shared = PublishedWordEntryVersion::factory()->for($external->wordEntry)->create([
        'shared_from_version_id' => $external->id,
        'content' => $external->content,
    ]);
    $this->post($start)->assertRedirect($show);
    expect($child->wordWheelAttempts()->sole()->published_word_entry_version_id)->toBe($shared->id);
    $this->get($show)->assertInertia(fn (Assert $page) => $page->where('attempt.clue', 'הר נמוך')->etc());
});

test('word wheel uses published suitability and wording while edits stay in the draft', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create(['learning_age' => 6, 'subjects' => ['טבע']]);
    $approved = [
        'word' => 'שמש',
        'meaning' => 'שמש בשמיים',
        'hints' => ['מאירה ביום'],
        'wheel_eligible' => true,
        'target_age' => 6,
        'subjects' => ['טבע'],
    ];
    $entry = WordEntry::factory()->for($team)->create(['content' => $approved]);

    $this->actingAs($parent);
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Livewire::test(ManageWordEntries::class)
        ->mountAction(TestAction::make('publish')->table($entry))
        ->assertSchemaStateSet(['content.wheel_eligible' => true, 'content.target_age' => 6, 'content.subjects' => ['טבע']])
        ->callMountedAction()
        ->assertNotified('הגרסה פורסמה');

    $version = $entry->publishedVersion()->sole();
    expect($version->content)->toEqual($approved);
    $entry->update(['content' => [...$approved, 'word' => 'לילה', 'wheel_eligible' => false, 'target_age' => 18]]);

    $show = route('word-wheel.play.show', $team);
    $this->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $child->id]])
        ->post(route('word-wheel.play.start', $team))->assertRedirect($show);
    expect($child->wordWheelAttempts()->sole()->published_word_entry_version_id)->toBe($version->id);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('attempt.clue', 'מאירה ביום')
        ->where('attempt.solution', null)
        ->etc()
    );
});

test('word wheel hides withdrawn boards and isolates attempts by the child session', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    $sibling = ChildProfile::factory()->for($team)->create();
    $otherParent = User::factory()->create();
    $otherTeam = $otherParent->currentTeam;
    $otherChild = ChildProfile::factory()->for($otherTeam)->create();
    $version = publishWheelTestWord($team, 'עיפרון', 'כותבים איתו על דף');
    publishWheelTestWord($team, 'ספר', 'קוראים בו סיפורים');

    $show = route('word-wheel.play.show', $team);
    $start = route('word-wheel.play.start', $team);
    $this->get($show)->assertRedirect(route('login'));
    $this->actingAs($parent)->get($show)->assertForbidden();
    $this->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $child->id]])
        ->post($start)->assertRedirect($show);
    $attempt = $child->wordWheelAttempts()->sole();

    $this->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $sibling->id]])
        ->get($show)->assertInertia(fn (Assert $page) => $page->where('attempt', null)->etc());
    $this->post(route('word-wheel.play.finish', [$team, $attempt]), ['revision' => 0])->assertNotFound();
    $this->actingAs($otherParent)
        ->withSession(['child_mode' => ['team_id' => $otherTeam->id, 'profile_id' => $otherChild->id]])
        ->post(route('word-wheel.play.finish', [$otherTeam, $attempt]), ['revision' => 0])->assertNotFound();

    $version->forceFill(['withdrawn_at' => now()])->save();
    $this->actingAs($parent)->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $child->id]])
        ->get($show)->assertInertia(fn (Assert $page) => $page->where('attempt', null)->where('poolAvailable', true)->etc());
    $this->post(route('word-wheel.play.finish', [$team, $attempt]), ['revision' => 0])->assertNotFound();
    $this->post($start)->assertRedirect($show);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('attempt.clue', 'קוראים בו סיפורים')
        ->where('attempt.solution', null)
        ->etc()
    );
});

test('word wheel honors household availability without interrupting an active attempt', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    publishWheelTestWord($team, 'אבן', 'חתיכה קשה מהאדמה');
    $game = Game::query()->where('key', 'word-wheel')->sole();
    $game->households()->attach($team, ['enabled' => false]);

    $show = route('word-wheel.play.show', $team);
    $start = route('word-wheel.play.start', $team);
    $this->actingAs($parent)->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $child->id]])
        ->from($show)->post($start)->assertSessionHasErrors('game');
    $game->households()->updateExistingPivot($team, ['enabled' => true]);
    $this->post($start)->assertRedirect($show);
    $attempt = $child->wordWheelAttempts()->sole();
    $game->update(['enabled' => false]);
    $this->get($show)->assertInertia(fn (Assert $page) => $page
        ->where('available', false)->where('attempt.id', $attempt->id)->etc());
    $this->post(route('word-wheel.play.solve', [$team, $attempt]), ['revision' => 0, 'solution' => 'אבן'])
        ->assertRedirect($show);
    $this->post($start)->assertSessionHasErrors('game');
    expect($child->wordWheelAttempts()->sole()->phase)->toBe('solved');
});
