<?php

use App\Actions\Catalog\PlayableQuestionVersions;
use App\Filament\Parent\Resources\Questions\Pages\ManageQuestions;
use App\Models\Question;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('review approval ignores JSON object ordering while detecting changed content', function () {
    $parent = User::factory()->create();
    $question = Question::factory()->for($parent->currentTeam)->create();
    $content = [...$question->content, 'prompt' => ['type' => 'text', 'value' => 'שאלה לבדיקה']];

    $this->actingAs($parent);
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    $question->update(['content' => $content]);
    $firstReview = Livewire::test(ManageQuestions::class)
        ->mountAction(TestAction::make('publish')->table($question));

    $reordered = array_reverse($content, preserve_keys: true);
    $reordered['prompt'] = array_reverse($reordered['prompt'], preserve_keys: true);
    $question->update(['content' => $reordered]);
    $firstReview->callMountedAction()->assertNotified('השאלה פורסמה');

    $staleReview = Livewire::test(ManageQuestions::class)
        ->mountAction(TestAction::make('publish')->table($question));
    $question->update(['content' => [...$content, 'prompt' => 'שאלה חדשה']]);
    $staleReview->callMountedAction()->assertNotified('השאלה השתנתה. יש לפתוח מחדש את הסקירה.');

    expect($question->versions()->sole()->content['prompt']['value'])->toBe('שאלה לבדיקה');
});

test('Filament publishes only complete localized snapshots of shared numbers and media', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    $disk = Storage::fake('quiz_media');
    $audio = "{$team->id}/word.wav";
    $disk->put($audio, file_get_contents(base_path('tests/Browser/Fixtures/tone.wav')));
    $question = Question::factory()->for($team)->create();
    $base = [
        'prompt' => ['type' => 'audio', 'path' => $audio, 'alternative' => 'צליל', 'shared' => true],
        'correct_answer' => ['type' => 'number', 'value' => '8'],
        'distractor_one' => ['type' => 'number', 'value' => '3'],
        'distractor_two' => ['type' => 'number', 'value' => '7'],
        'distractor_three' => 'ברווז',
        'explanation' => 'שמונה היא התשובה.',
    ];
    $question->update(['content' => $base, 'quiz_eligible' => true]);

    $this->actingAs($parent);
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Livewire::test(ManageQuestions::class)
        ->callAction(TestAction::make('publish')->table($question))
        ->assertNotified('השאלה פורסמה');

    $pool = fn (string $locale) => app(PlayableQuestionVersions::class)(
        $team->id, fn (Builder $query) => $query->where('quiz_eligible', true), $locale,
    );
    expect($pool('he')->sole()->content['correct_answer']['value'])->toBe('8');
    expect($pool('en'))->toBeEmpty();

    $edit = TestAction::make('editLocale')->table($question);
    $review = TestAction::make('publishLocale')->table($question);
    $localized = [
        'explanation' => 'Eight is the answer.',
        'prompt' => ['type' => 'inherit', 'alternative' => 'A sound'],
        'distractor_three' => ['type' => 'text', 'value' => 'Duck'],
    ];

    foreach (['prompt', 'distractor_three'] as $field) {
        Livewire::test(ManageQuestions::class)->callAction($edit, data: [
            'locale' => 'en',
            'content' => [...$localized, $field => [...$localized[$field], 'value' => null, 'alternative' => null]],
        ]);
        Livewire::test(ManageQuestions::class)->callAction($review);
        expect($pool('en'))->toBeEmpty();
    }

    Livewire::test(ManageQuestions::class)->callAction($edit, data: [
        'locale' => 'en', 'content' => $localized,
    ]);
    $question->update(['content' => [...$base, 'prompt' => [...$base['prompt'], 'shared' => false]]]);
    Livewire::test(ManageQuestions::class)->callAction($review);
    expect($pool('en'))->toBeEmpty();

    $question->update(['content' => $base]);
    $preview = route('parent.questions.media', [
        'question' => $question,
        'field' => 'prompt',
        'locale' => 'en',
        'revision' => $question->refresh()->draftRevision('en'),
    ]);
    $this->get($preview)->assertOk();

    Livewire::test(ManageQuestions::class)
        ->mountAction($review)
        ->assertSchemaStateSet(['locale' => 'en', 'content.prompt.alternative' => 'A sound', 'content.correct_answer.value' => '8'])
        ->callMountedAction()
        ->assertNotified('השאלה פורסמה');

    expect($pool('en')->sole()->content['prompt'])->toMatchArray([
        'type' => 'audio', 'path' => $audio, 'alternative' => 'A sound',
    ]);
    expect($pool('en')->sole()->content['distractor_three']['value'])->toBe('Duck');

    $stale = Livewire::test(ManageQuestions::class)->mountAction($review);
    $question->update(['content' => [...$base, 'correct_answer' => ['type' => 'number', 'value' => '9']]]);
    $stale->callMountedAction()->assertNotified('השאלה השתנתה. יש לפתוח מחדש את הסקירה.');
    $this->get($preview)->assertConflict();
    expect($pool('en')->sole()->content['correct_answer']['value'])->toBe('8');

    Livewire::test(ManageQuestions::class)
        ->callAction(TestAction::make('publish')->table($question))
        ->assertNotified('השאלה פורסמה');
    expect($pool('he')->sole()->content['correct_answer']['value'])->toBe('9');
    expect($pool('en')->sole()->content['correct_answer']['value'])->toBe('8');

    Livewire::test(ManageQuestions::class)->callAction($review)->assertNotified('השאלה פורסמה');
    expect($pool('en')->sole()->content['correct_answer']['value'])->toBe('9');
});

test('a language with its own override keeps its review when the shared value changes', function () {
    $parent = User::factory()->create();
    $question = Question::factory()->for($parent->currentTeam)->create();
    $base = [...$question->content, 'prompt' => ['type' => 'number', 'value' => '8']];
    $question->forceFill([
        'content' => $base,
        'localizations' => ['en' => [
            'prompt' => ['type' => 'number', 'value' => '3'],
            'correct_answer' => 'Three',
            'distractor_one' => 'Two',
            'distractor_two' => 'Eight',
            'distractor_three' => 'Nine',
            'explanation' => 'The number shown is three.',
        ]],
    ])->save();

    $this->actingAs($parent);
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    $review = Livewire::test(ManageQuestions::class)
        ->mountAction(TestAction::make('publishLocale')->table($question))
        ->assertSchemaStateSet(['locale' => 'en', 'content.prompt.value' => '3']);

    $question->update(['content' => [...$base, 'prompt' => ['type' => 'number', 'value' => '6']]]);
    $review->callMountedAction()->assertNotified('השאלה פורסמה');

    expect($question->publishedVersion('en')->sole()->content['prompt']['value'])->toBe('3');
});
