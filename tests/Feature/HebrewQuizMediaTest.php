<?php

use App\Actions\Catalog\CopySharedQuestion;
use App\Actions\Catalog\PublishCatalogVersion;
use App\Actions\Catalog\ShareCatalogVersion;
use App\Actions\Catalog\WithdrawQuestionVersion;
use App\Models\ChildProfile;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\HebrewQuizSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

test('media requests resolve the active pinned version and exclude drafts and withdrawn files', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    $other = ChildProfile::factory()->for($team)->create();
    app(HebrewQuizSeeder::class)->run($team);

    $disk = Storage::fake('quiz_media');
    $audio = file_get_contents(base_path('tests/Browser/Fixtures/tone.wav'));
    $path = "{$team->id}/first.wav";
    $draftPath = "{$team->id}/draft.wav";
    $disk->put($path, $audio);
    $disk->put($draftPath, $audio.'draft');

    $question = Question::factory()->for($team)->create();
    $content = [
        ...$question->content,
        'prompt' => ['type' => 'audio', 'path' => $path, 'alternative' => 'האזינו לטון.'],
        'sources' => ['https://example.com/reviewer-source'],
    ];
    $question->update(['content' => $content]);

    $this->actingAs($parent);
    $version = app(PublishCatalogVersion::class)($question, $question->draftRevision());
    $question->update(['content' => [...$content, 'prompt' => ['type' => 'audio', 'path' => $draftPath, 'alternative' => 'טיוטה']]]);

    $reviewMedia = route('parent.questions.media', ['question' => $question, 'field' => 'prompt']);
    $this->get($reviewMedia)->assertOk();
    $this->actingAs(User::factory()->create())->get($reviewMedia)->assertNotFound();

    $this->actingAs($parent)->withSession(['child_mode' => ['profile_id' => $child->id, 'team_id' => $team->id]])
        ->post(route('quiz.play.start', $team))->assertRedirect(route('quiz.play.show', $team));
    $run = $child->quizRuns()->sole();
    $attempt = $run->questions()->firstOrFail();
    $media = route('quiz.play.media', [
        'current_team' => $team,
        'run' => $run->id,
        'question' => $attempt->id,
        'slot' => 'prompt',
    ], absolute: false);

    $this->get(route('quiz.play.show', $team))->assertInertia(fn (Assert $page) => $page
        ->where('question.prompt.type', 'audio')
        ->where('question.prompt.alternative', 'האזינו לטון.')
        ->where('question.prompt.url', $media)
        ->missing('question.prompt.path')
        ->missing('question.sources')
        ->etc());

    $this->get($media, ['Range' => 'bytes=0-7'])
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 0-7/'.strlen($audio))
        ->assertHeader('Accept-Ranges', 'bytes');
    $this->get($reviewMedia)->assertRedirect(route('child-mode.exit'));
    $this->withSession(['child_mode' => ['profile_id' => $other->id, 'team_id' => $team->id]])
        ->get($media)->assertNotFound();

    app(WithdrawQuestionVersion::class)($question, $version->id);

    $this->withSession(['child_mode' => ['profile_id' => $child->id, 'team_id' => $team->id]])
        ->get($media)->assertNotFound();
});

test('a parent cannot publish an unrelated household file or active content disguised as media', function () {
    $parent = User::factory()->create();
    $other = User::factory()->create();
    $team = $parent->currentTeam;
    $disk = Storage::fake('quiz_media');
    $foreign = "{$other->current_team_id}/foreign.wav";
    $invalid = "{$team->id}/document.wav";
    $disk->put($foreign, file_get_contents(base_path('tests/Browser/Fixtures/tone.wav')));
    $disk->put($invalid, '<html><title>Not audio</title></html>');

    $question = Question::factory()->for($team)->create();
    $this->actingAs($parent);

    foreach ([$foreign, $invalid] as $path) {
        $question->update(['content' => [
            ...$question->content,
            'prompt' => ['type' => 'audio', 'path' => $path, 'alternative' => 'טון קצר'],
        ]]);

        expect(fn () => app(PublishCatalogVersion::class)($question, $question->draftRevision()))
            ->toThrow(ValidationException::class);
    }

    expect($question->versions()->exists())->toBeFalse();
});

test('a shared catalog approval previews exact media and a private adaptation can reuse it', function () {
    $contributor = User::factory()->create();
    $parent = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $team = $parent->currentTeam;
    $child = ChildProfile::factory()->for($team)->create();
    app(HebrewQuizSeeder::class)->run($team);

    $path = "{$contributor->current_team_id}/shared.wav";
    Storage::fake('quiz_media')->put($path, file_get_contents(base_path('tests/Browser/Fixtures/tone.wav')));
    $question = Question::factory()->for($contributor->currentTeam)->create();
    $question->update(['content' => [
        ...$question->content,
        'prompt' => ['type' => 'audio', 'path' => $path, 'alternative' => 'טון מהקטלוג'],
    ]]);

    $this->actingAs($contributor);
    $version = app(PublishCatalogVersion::class)($question, $question->draftRevision());
    $adminPreview = route('admin.catalog-versions.media', ['version' => $version, 'field' => 'prompt']);
    $this->get($adminPreview)->assertForbidden();
    $this->actingAs($admin)->get($adminPreview)->assertOk()->assertHeader('Content-Disposition', 'inline');
    app(ShareCatalogVersion::class)($version);

    $this->actingAs($parent);
    $copy = app(CopySharedQuestion::class)($question, $team);
    $copyVersion = app(PublishCatalogVersion::class)($copy, $copy->draftRevision());

    $this->withSession(['child_mode' => ['profile_id' => $child->id, 'team_id' => $team->id]])
        ->post(route('quiz.play.start', $team))->assertRedirect(route('quiz.play.show', $team));

    $run = $child->quizRuns()->sole();
    $attempt = $run->questions()->firstOrFail();
    expect($attempt->published_question_version_id)->toBe($copyVersion->id);
    $this->get(route('quiz.play.media', ['current_team' => $team, 'run' => $run->id, 'question' => $attempt->id, 'slot' => 'prompt']))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline');
});
