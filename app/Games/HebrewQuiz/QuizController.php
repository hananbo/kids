<?php

namespace App\Games\HebrewQuiz;

use App\Models\ChildProfile;
use App\Models\QuizRun;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuizController
{
    public function show(Request $request, Team $current_team): Response
    {
        $profile = $this->profile($request, $current_team);
        $run = $this->runs($profile)->latest('id')->first();

        $question = $run && ! $run->completed_at
            ? $run->questions()->with('version')->where('position', $run->position)->firstOrFail()
            : null;

        $content = $question?->version->content;

        return Inertia::render('games/hebrew-quiz', [
            'team' => $current_team->slug,
            'player' => $profile->name,
            'run' => $run ? [
                'id' => $run->id,
                'position' => $run->position + 1,
                'total' => $run->questions()->count(),
                'completed' => $run->completed_at !== null,
                'score' => $run->completed_at ? $run->questions()->where('is_correct', true)->count() : null,
            ] : null,
            'question' => $question && $content ? [
                'id' => $question->id,
                'prompt' => $content['prompt'],
                'choices' => array_map(fn (string $key): string => $content[$key], $question->choice_order),
                'feedback' => $question->answered_at ? [
                    'selected' => $question->selected_choice,
                    'correct' => $question->is_correct,
                    'answer' => $content['correct_answer'],
                    'explanation' => $content['explanation'],
                ] : null,
            ] : null,
        ]);
    }

    public function start(Request $request, Team $current_team, StartQuizRun $start): RedirectResponse
    {
        $start($this->profile($request, $current_team));

        return redirect()->route('quiz.play.show', $current_team);
    }

    public function answer(Request $request, Team $current_team, int $run, RecordQuizAnswer $answer): RedirectResponse
    {
        $selectedRun = $this->runs($this->profile($request, $current_team))->findOrFail($run);
        $input = $request->validate(['question' => ['required', 'integer'], 'choice' => ['required', 'integer', 'between:0,3']]);

        $answer($selectedRun, (int) $input['question'], (int) $input['choice']);

        return redirect()->route('quiz.play.show', $current_team);
    }

    public function advance(Request $request, Team $current_team, int $run, AdvanceQuizRun $advance): RedirectResponse
    {
        $selectedRun = $this->runs($this->profile($request, $current_team))->findOrFail($run);
        $input = $request->validate(['question' => ['required', 'integer']]);

        $advance($selectedRun, (int) $input['question']);

        return redirect()->route('quiz.play.show', $current_team);
    }

    private function profile(Request $request, Team $team): ChildProfile
    {
        abort_unless($request->session()->get('child_mode.team_id') === $team->id, 403);
        $profileId = $request->session()->get('child_mode.profile_id');
        abort_unless(is_int($profileId), 403);
        Gate::authorize('update', $team);

        return ChildProfile::query()
            ->whereBelongsTo($team)
            ->findOrFail($profileId);
    }

    /** @return HasMany<QuizRun, ChildProfile> */
    private function runs(ChildProfile $profile): HasMany
    {
        return $profile->quizRuns()->where('game', SelectQuizQuestions::GAME);
    }
}
