<?php

use App\Filament\Parent\Resources\WordEntries\Pages\ManageWordEntries;
use App\Filament\Resources\CatalogWordEntryVersions\Pages\ListCatalogWordEntryVersions;
use App\Models\ChildProfile;
use App\Models\PublishedWordEntryVersion;
use App\Models\User;
use App\Models\WordEntry;
use Database\Seeders\HebrewQuizSeeder;
use Database\Seeders\WordEntrySeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

test('word entries stay household private until a platform admin shares the exact approved language', function () {
    $owner = User::factory()->create();
    $reader = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($owner)->get('/parent/word-entries')->assertOk();

    $word = WordEntry::factory()->for($owner->currentTeam)->create([
        'content' => ['word' => 'הר געש', 'meaning' => 'הר שמתפרצת ממנו לבה', 'hints' => ['לבה']],
    ]);

    $this->get('/parent/word-entries')->assertSee('הר געש');
    $this->actingAs($reader)->get('/parent/word-entries')->assertDontSee('הר געש');
    $this->get('/parent/shared-word-entries')->assertDontSee('הר געש');
    $this->get('/admin/catalog-word-entry-versions')->assertForbidden();

    $this->actingAs($owner);
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Livewire::test(ManageWordEntries::class)
        ->callAction(TestAction::make('publish')->table($word))
        ->assertNotified('הגרסה פורסמה')
        ->callAction(TestAction::make('editLocale')->table($word), data: [
            'locale' => 'en',
            'content' => ['word' => 'Volcano', 'meaning' => 'A vent where molten rock reaches the surface', 'hints' => ['Lava']],
        ])
        ->assertNotified('טיוטת התרגום נשמרה');

    expect($word->publishedVersion('en')->exists())->toBeFalse();

    Livewire::test(ManageWordEntries::class)
        ->mountAction(TestAction::make('publishLocale')->table($word))
        ->assertSchemaStateSet(['locale' => 'en', 'content.word' => 'Volcano'])
        ->callMountedAction()
        ->assertNotified('הגרסה פורסמה');

    $staleReview = Livewire::test(ManageWordEntries::class)
        ->mountAction(TestAction::make('publishLocale')->table($word));

    Livewire::test(ManageWordEntries::class)
        ->callAction(TestAction::make('editLocale')->table($word), data: [
            'locale' => 'en',
            'content' => ['word' => 'Volcanic vent', 'meaning' => 'A new translation awaiting review'],
        ])
        ->assertNotified('טיוטת התרגום נשמרה');

    $staleReview->callMountedAction()->assertNotified('הערך השתנה. יש לפתוח מחדש את הסקירה.');

    $approved = $word->publishedVersion('en')->sole();
    expect($approved->content['word'])->toBe('Volcano');
    expect($word->publishedVersion()->sole()->content['word'])->toBe('הר געש');

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ListCatalogWordEntryVersions::class)
        ->mountAction(TestAction::make('share')->table($approved))
        ->assertSchemaStateSet([
            'content.word' => 'Volcano',
            'content.meaning' => 'A vent where molten rock reaches the surface',
            'content.hints' => ['Lava'],
        ])
        ->callMountedAction()
        ->assertNotified('Word Entry version shared');

    $this->actingAs($reader)->get('/parent/shared-word-entries')
        ->assertOk()
        ->assertSee('Volcano')
        ->assertDontSee('Volcanic vent')
        ->assertDontSee('הר געש');

    $shared = PublishedWordEntryVersion::query()->current(shared: true)->sole();
    expect($shared->content)->toBe($approved->content)
        ->and($shared->shared_from_version_id)->toBe($approved->id);
});

test('shared word entries do not supply the Hebrew quiz question pool', function () {
    $contributor = User::factory()->create();
    $reader = User::factory()->create();
    $team = $reader->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    app(WordEntrySeeder::class)->run($contributor->currentTeam);
    $word = WordEntry::query()->whereBelongsTo($contributor->currentTeam)->sole();
    $published = $word->publishedVersion()->sole();
    PublishedWordEntryVersion::factory()->for($word)->create(['shared_from_version_id' => $published->id]);
    $play = route('quiz.play.show', $team);

    $this->actingAs($reader)->get('/parent/shared-word-entries')
        ->assertSee($published->content['word']);

    $this->withSession(['child_mode' => ['team_id' => $team->id, 'profile_id' => $child->id]])
        ->from($play)
        ->post(route('quiz.play.start', $team))
        ->assertRedirect($play)
        ->assertSessionHasErrors(['pool' => implode(' ', [
            'רמת קושי 1: 0 מתוך 3 שאלות מאושרות.',
            'רמת קושי 2: 0 מתוך 3 שאלות מאושרות.',
            'רמת קושי 3: 0 מתוך 2 שאלות מאושרות.',
        ])]);

    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('run', null)
        ->where('question', null)
        ->etc()
    );

    app(HebrewQuizSeeder::class)->run($team);
    $this->post(route('quiz.play.start', $team))->assertRedirect($play);
    $this->get($play)->assertInertia(fn (Assert $page) => $page
        ->where('run.total', 15)
        ->where('question.prompt.value', fn (string $prompt): bool => $prompt !== $published->content['word'])
        ->etc()
    );
});
