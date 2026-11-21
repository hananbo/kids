<?php

use App\Models\ChildProfile;
use App\Models\PublishedQuestionVersion;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\HebrewQuizSeeder;
use Illuminate\Support\Facades\Storage;

test('quiz music starts on a gesture, yields to question audio and remembers mute on reload', function () {
    $parent = User::factory()->create();
    $team = $parent->currentTeam;
    ChildProfile::factory()->for($team)->create();
    app(HebrewQuizSeeder::class)->run($team);

    $mediaPath = "{$team->id}/tone.wav";
    Storage::fake('quiz_media')->put($mediaPath, file_get_contents(__DIR__.'/Fixtures/tone.wav'));
    $question = Question::factory()->for($team)->create();
    PublishedQuestionVersion::factory()->for($question)->create([
        'content' => [
            ...$question->content,
            'prompt' => ['type' => 'audio', 'path' => $mediaPath, 'alternative' => 'צליל לשאלה'],
        ],
    ]);

    $page = visit(route('login'))
        ->type('email', $parent->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs("/{$team->slug}/dashboard")
        ->navigate('/parent/child-profiles')
        ->click('[data-test="select-child-hebrew-quiz"]')
        ->assertPresent('main[data-stage="welcome"]')
        ->assertAttribute('[data-test="quiz-sound"]', 'aria-pressed', 'false')
        ->click('[data-test="start-run"]')
        ->assertAttribute('[data-test="quiz-sound"]', 'aria-pressed', 'true')
        ->click('[data-test="open-question"]')
        ->assertPresent('main[data-stage="playing"]')
        ->assertPresent('audio[controls]')
        ->refresh()
        ->assertPresent('[data-test="open-question"]')
        ->assertAttribute('[data-test="quiz-sound"]', 'aria-pressed', 'false');

    $page->script(<<<'JS'
        () => {
            const connect = AudioNode.prototype.connect;

            AudioNode.prototype.connect = function (destination, ...ports) {
                if (destination === this.context.destination) {
                    window.quizOutput = this.context.createAnalyser();
                    connect.call(this, window.quizOutput);
                }

                return connect.call(this, destination, ...ports);
            };

            window.quizPeak = async (startAt = 0) => {
                const samples = new Float32Array(window.quizOutput.fftSize);
                let peak = 0;

                while (window.quizOutput.context.currentTime < startAt) {
                    await new Promise(requestAnimationFrame);
                }

                for (let frame = 0; frame < 30; frame++) {
                    await new Promise(requestAnimationFrame);
                    window.quizOutput.getFloatTimeDomainData(samples);
                    peak = Math.max(peak, ...samples.map(Math.abs));
                }

                return peak;
            };
        }
        JS);

    $page->click('[data-test="open-question"]')
        ->assertPresent('audio[controls]')
        ->assertAttribute('[data-test="quiz-sound"]', 'aria-pressed', 'true');

    expect($page->script('window.quizPeak(0.5)'))->toBeGreaterThan(0.01);

    expect($page->script(<<<'JS'
        async () => {
            const media = document.querySelector('audio');
            media.loop = true;
            await media.play();

            const probe = window.quizOutput;
            return await window.quizPeak(probe.context.currentTime + probe.fftSize / probe.context.sampleRate);
        }
        JS))->toBeLessThan(0.001);

    expect($page->script(<<<'JS'
        async () => {
            document.querySelector('audio').pause();

            return await window.quizPeak();
        }
        JS))->toBeGreaterThan(0.01);

    $page->click('[data-test="quiz-sound"]')
        ->assertAttribute('[data-test="quiz-sound"]', 'aria-pressed', 'false');

    expect($page->script('window.quizOutput.context.state'))->toBe('closed');

    $page->refresh()
        ->click('[data-test="open-question"]')
        ->assertPresent('main[data-stage="playing"]')
        ->assertAttribute('[data-test="quiz-sound"]', 'aria-pressed', 'false')
        ->assertNoJavaScriptErrors();
});
