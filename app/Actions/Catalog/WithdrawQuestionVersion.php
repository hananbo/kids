<?php

namespace App\Actions\Catalog;

use App\Models\Question;
use App\Models\QuizRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WithdrawQuestionVersion
{
    public function __invoke(Question $question, int $versionId): void
    {
        DB::transaction(function () use ($question, $versionId): void {
            $version = $question->versions()->lockForUpdate()->findOrFail($versionId);

            if ($version->shared_from_version_id !== null) {
                Gate::authorize('manage-shared-catalog');
            } else {
                Gate::authorize('update', $question->team);
            }

            if ($version->withdrawn_at !== null) {
                return;
            }

            $withdrawnAt = now();
            $version->forceFill(['withdrawn_at' => $withdrawnAt])->save();

            QuizRun::query()
                ->active()
                ->whereHas('questions', fn (Builder $query): Builder => $query->where('published_question_version_id', $version->id))
                ->update(['cancelled_at' => $withdrawnAt]);
        }, attempts: 3);
    }
}
