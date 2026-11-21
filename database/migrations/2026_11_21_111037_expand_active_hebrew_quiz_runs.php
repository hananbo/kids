<?php

use App\Actions\Catalog\PlayableQuestionVersions;
use App\Games\HebrewQuiz\PrizeLadder;
use App\Games\HebrewQuiz\RecordQuizAnswer;
use App\Games\HebrewQuiz\SelectQuizQuestions;
use App\Models\ChildProfile;
use App\Models\PublishedQuestionVersion;
use App\Models\QuizRun;
use App\Models\QuizRunQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $lastExistingRun = QuizRun::query()->max('id');
        $shortLength = count(PrizeLadder::SHORT);

        $runs = QuizRun::query()
            ->where('game', SelectQuizQuestions::GAME)
            ->where('id', '<=', $lastExistingRun ?? 0)
            ->active()
            ->has('questions', '=', $shortLength)
            ->lazyById(100);

        foreach ($runs as $run) {
            DB::transaction(fn () => $this->expand($run, $shortLength), attempts: 3);
        }
    }

    private function expand(QuizRun $run, int $shortLength): void
    {
        $profile = ChildProfile::query()->sharedLock()->find($run->child_profile_id);
        $pinned = $run->questions()->with('version:id,question_id,difficulty')->get();
        $last = $pinned->last();

        if (! $profile || $pinned->count() !== $shortLength || $last->position !== $shortLength - 1) {
            return;
        }

        $needed = count(PrizeLadder::FULL) - $shortLength;
        $candidates = app(SelectQuizQuestions::class)->extensionPool(
            $profile,
            $run->level,
            $last->version->difficulty,
            array_values($pinned->map(fn (QuizRunQuestion $question): int => $question->version->question_id)->all()),
        );

        if ($candidates->count() < $needed) {
            return;
        }

        // Withdrawal locks the version before it cancels affected runs.
        $available = app(PlayableQuestionVersions::class)($profile->team_id, fn (Builder $query): Builder => $query
            ->whereKey($candidates->pluck('id'))
            ->where('quiz_eligible', true)
            ->orderBy('id')
            ->sharedLock())->pluck('id');

        $additional = $candidates->whereIn('id', $available)
            ->unique('question_id')
            ->take($needed)
            ->values();

        if ($additional->count() !== $needed) {
            return;
        }

        $active = QuizRun::query()->active()->lockForUpdate()->find($run->id);

        if (! $active || $active->questions()->where('position', $shortLength)->lockForUpdate()->first(['id'])) {
            return;
        }

        $active->questions()->createMany($additional->map(fn (PublishedQuestionVersion $version, int $offset): array => [
            'published_question_version_id' => $version->id,
            'position' => $shortLength + $offset,
            'choice_order' => Arr::shuffle(RecordQuizAnswer::CHOICES),
        ])->all());
    }

    public function down(): void
    {
        // Appended questions may now have answers, so rollback preserves their history.
    }
};
