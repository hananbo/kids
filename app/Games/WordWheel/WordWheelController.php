<?php

namespace App\Games\WordWheel;

use App\Http\Middleware\EnsureChildPlay;
use App\Models\Game;
use App\Models\Team;
use App\Models\WordWheelAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WordWheelController
{
    public function __construct(private PlayWheelAttempt $playAttempt) {}

    public function show(Request $request, Team $current_team, SelectWheelPuzzle $selectPuzzle): Response
    {
        $profile = EnsureChildPlay::profile($request);
        $attempt = $profile->wordWheelAttempts()->with('version')->latest('id')->first();
        $records = $profile->wordWheelAttempts()->where('phase', 'solved')
            ->selectRaw('COUNT(*) as solved_count, MAX(score) as best_score')->first();

        return Inertia::render('games/word-wheel', [
            'teamSlug' => $current_team->slug,
            'childName' => $profile->name,
            'available' => Game::allowsStart(SelectWheelPuzzle::GAME, $profile->team_id),
            'poolAvailable' => $selectPuzzle($profile) !== null,
            'segments' => SpinWheel::SEGMENTS,
            'attempt' => $attempt ? $this->board($attempt) : null,
            'progress' => [
                'solved' => (int) $records?->getAttribute('solved_count'),
                'bestScore' => (int) $records?->getAttribute('best_score'),
            ],
        ]);
    }

    public function start(Request $request, Team $current_team, StartWheelAttempt $start): RedirectResponse
    {
        $start(EnsureChildPlay::profile($request));

        return redirect()->route('word-wheel.play.show', $current_team);
    }

    public function spin(Request $request, Team $current_team, int $attempt): RedirectResponse
    {
        return $this->play($request, $current_team, $attempt, 'spin');
    }

    public function guess(Request $request, Team $current_team, int $attempt): RedirectResponse
    {
        $data = $request->validate(['letter' => ['required', 'string', 'regex:/^[א-ת]$/uD']]);

        return $this->play($request, $current_team, $attempt, 'guess', $data['letter']);
    }

    public function solve(Request $request, Team $current_team, int $attempt): RedirectResponse
    {
        $data = $request->validate(['solution' => ['required', 'string', 'max:100']]);

        return $this->play($request, $current_team, $attempt, 'solve', $data['solution']);
    }

    public function finish(Request $request, Team $current_team, int $attempt): RedirectResponse
    {
        return $this->play($request, $current_team, $attempt, 'finish');
    }

    /** @param 'spin'|'guess'|'solve'|'finish' $move */
    private function play(Request $request, Team $team, int $attempt, string $move, string $input = ''): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        ($this->playAttempt)(EnsureChildPlay::profile($request), $attempt, (int) $data['revision'], $move, $input);

        return redirect()->route('word-wheel.play.show', $team);
    }

    /** @return array<string, mixed>|null */
    private function board(WordWheelAttempt $attempt): ?array
    {
        $version = $attempt->version;

        if ($version === null || $version->withdrawn_at !== null) {
            return null;
        }

        $content = $version->content;
        $word = is_string($content['word'] ?? null) ? $content['word'] : '';
        $answer = WheelPuzzle::text($word);
        $reveal = ! $attempt->isActive();

        return [
            'id' => $attempt->id,
            'revision' => $attempt->revision,
            'phase' => $attempt->phase,
            'score' => $attempt->score,
            'board' => WheelPuzzle::board($answer, $attempt->guessed_letters, $reveal),
            'clue' => WheelPuzzle::clue($content, $answer),
            'guessedLetters' => $attempt->guessed_letters,
            'spinIndex' => $attempt->spin_index,
            'spins' => $attempt->spins,
            'feedback' => $attempt->feedback,
            'solution' => $reveal ? $word : null,
        ];
    }
}
