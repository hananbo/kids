<?php

namespace App\Games\HebrewQuiz;

use App\Http\Middleware\EnsureChildPlay;
use App\Models\ChildProfile;
use App\Models\Game;
use App\Models\QuizRun;
use App\Models\QuizRunQuestion;
use App\Models\Team;
use App\Support\Catalog\QuestionContent;
use App\Support\Catalog\QuestionMedia;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuizController
{
    public function show(Request $request, Team $current_team, QuizProgress $progress): Response
    {
        $profile = EnsureChildPlay::profile($request);
        $run = $this->runs($profile)->latest('id')->first();

        $question = $run?->isActive()
            ? $run->questions()->with('version')->where('position', $run->position)->firstOrFail()
            : null;

        $total = $run?->questions()->count() ?? 0;
        $prizes = $total === count(PrizeLadder::FULL) ? PrizeLadder::FULL : PrizeLadder::SHORT;
        $stars = $run?->stars() ?? 0;
        $cleared = $run ? $run->position + (int) ($question?->is_correct === true || $run->outcome === 'won') : 0;
        $earned = $prizes[$cleared - 1] ?? 0;
        $lost = $run?->outcome === 'lost' || ($question?->answered_at && ! $question->is_correct);
        $lifelines = $run->lifelines ?? [];
        $content = $question?->version->content;

        return Inertia::render('games/hebrew-quiz', [
            'team' => $current_team->slug,
            'player' => $profile->name,
            'available' => Game::allowsStart(SelectQuizQuestions::GAME, $current_team->id),
            'progress' => [
                'highestLevel' => $progress->highestLevel($profile),
                'stars' => $progress->completedStars($profile),
            ],
            'run' => $run ? [
                'level' => $run->level,
                'unlockedLevel' => $run->unlocked_level,
                'stars' => $stars,
                'prizes' => $prizes,
                'safetyPrizes' => PrizeLadder::GUARANTEES,
                'payout' => PrizeLadder::payout($earned, $lost),
                'outcome' => $run->outcome,
                'id' => $run->id,
                'position' => $run->position + 1,
                'total' => $total,
                'completed' => $run->completed_at !== null,
                'cancelled' => $run->cancelled_at !== null,
            ] : null,
            'question' => $run && $question && $content ? [
                'id' => $question->id,
                'lifelines' => [
                    'fiftyUsed' => isset($lifelines['fifty']),
                    'advisorUsed' => isset($lifelines['advisor']) || $run->hint_position !== null,
                    'advisorChoice' => ($lifelines['advisor']['question'] ?? null) === $question->id ? $lifelines['advisor']['result'][0] : null,
                    'audienceUsed' => isset($lifelines['audience']),
                    'eliminated' => ($lifelines['fifty']['question'] ?? null) === $question->id ? $lifelines['fifty']['result'] : [],
                    'votes' => ($lifelines['audience']['question'] ?? null) === $question->id ? $lifelines['audience']['result'] : null,
                ],
                'hintAvailable' => $run->canRevealHintFor($question),
                'hint' => $run->hint_position === $question->position ? $question->approvedHint() : null,
                'prompt' => $this->playerField($current_team, $run, $question, 'prompt'),
                'choices' => array_map(fn (int $slot): array => $this->playerField($current_team, $run, $question, (string) $slot), array_keys($question->choice_order)),
                'feedback' => $question->answered_at ? [
                    'selected' => $question->selected_choice,
                    'correctIndex' => array_search('correct_answer', $question->choice_order, true),
                    'correct' => $question->is_correct,
                    'answer' => QuestionContent::label($content['correct_answer']),
                    'explanation' => $content['explanation'],
                ] : null,
            ] : null,
        ]);
    }

    public function start(Request $request, Team $current_team, StartQuizRun $start): RedirectResponse
    {
        $start(EnsureChildPlay::profile($request));

        return redirect()->route('quiz.play.show', $current_team);
    }

    public function answer(Request $request, Team $current_team, int $run, RecordQuizAnswer $answer): RedirectResponse
    {
        $selectedRun = $this->runs(EnsureChildPlay::profile($request))->findOrFail($run);
        $input = $request->validate(['question' => ['required', 'integer'], 'choice' => ['required', 'integer', 'between:0,3']]);

        $answer($selectedRun, (int) $input['question'], (int) $input['choice']);

        return redirect()->route('quiz.play.show', $current_team);
    }

    public function hint(Request $request, Team $current_team, int $run, RevealQuizHint $revealHint): RedirectResponse
    {
        $selectedRun = $this->runs(EnsureChildPlay::profile($request))->findOrFail($run);
        $input = $request->validate(['question' => ['required', 'integer']]);

        $revealHint($selectedRun, (int) $input['question']);

        return redirect()->route('quiz.play.show', $current_team);
    }

    public function walk(Request $request, Team $current_team, int $run, WalkAwayFromQuiz $walk): RedirectResponse
    {
        $selectedRun = $this->runs(EnsureChildPlay::profile($request))->findOrFail($run);
        $input = $request->validate(['question' => ['required', 'integer']]);
        $walk($selectedRun, (int) $input['question']);

        return redirect()->route('quiz.play.show', $current_team);
    }

    public function lifeline(Request $request, Team $current_team, int $run, UseQuizLifeline $use): RedirectResponse
    {
        $selectedRun = $this->runs(EnsureChildPlay::profile($request))->findOrFail($run);
        $input = $request->validate(['question' => ['required', 'integer'], 'kind' => ['required', 'in:fifty,audience,advisor']]);
        $use($selectedRun, (int) $input['question'], $input['kind']);

        return redirect()->route('quiz.play.show', $current_team);
    }

    public function advance(Request $request, Team $current_team, int $run, AdvanceQuizRun $advance): RedirectResponse
    {
        $selectedRun = $this->runs(EnsureChildPlay::profile($request))->findOrFail($run);
        $input = $request->validate(['question' => ['required', 'integer']]);

        $advance($selectedRun, (int) $input['question']);

        return redirect()->route('quiz.play.show', $current_team);
    }

    public function media(Request $request, Team $current_team, int $run, int $question, string $slot): BinaryFileResponse
    {
        $activeRun = $this->runs(EnsureChildPlay::profile($request))->active()->findOrFail($run);
        $attempt = $activeRun->questions()->with('version')->where('position', $activeRun->position)->findOrFail($question);
        abort_if($attempt->version->withdrawn_at !== null, 404);

        return QuestionMedia::response($this->fieldForSlot($attempt, $slot));
    }

    /** @return array{type: string, value?: string, alternative?: string, url?: string} */
    private function playerField(Team $team, QuizRun $run, QuizRunQuestion $attempt, string $slot): array
    {
        $field = $this->fieldForSlot($attempt, $slot);

        if (isset($field['path'])) {
            return [
                'type' => $field['type'],
                'alternative' => $field['alternative'] ?? '',
                'url' => route('quiz.play.media', [
                    'current_team' => $team,
                    'run' => $run->id,
                    'question' => $attempt->id,
                    'slot' => $slot,
                ], absolute: false),
            ];
        }

        return ['type' => $field['type'], 'value' => $field['value'] ?? ''];
    }

    /** @return array{type: string, value?: string, path?: string, alternative?: string} */
    private function fieldForSlot(QuizRunQuestion $attempt, string $slot): array
    {
        $key = $slot === 'prompt' ? 'prompt' : $attempt->choice_order[(int) $slot];

        return QuestionContent::field($attempt->version->content[$key] ?? null);
    }

    /** @return HasMany<QuizRun, ChildProfile> */
    private function runs(ChildProfile $profile): HasMany
    {
        return $profile->quizRuns()->where('game', SelectQuizQuestions::GAME);
    }
}
