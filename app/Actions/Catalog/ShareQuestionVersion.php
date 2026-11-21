<?php

namespace App\Actions\Catalog;

use App\Models\PublishedQuestionVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ShareQuestionVersion
{
    public function __invoke(PublishedQuestionVersion $version): void
    {
        Gate::authorize('manage-shared-catalog');

        DB::transaction(function () use ($version): void {
            $version = PublishedQuestionVersion::query()
                ->whereNull('withdrawn_at')
                ->whereNull('shared_from_version_id')
                ->lockForUpdate()
                ->findOrFail($version->id);

            if ($version->sharedCopy()->doesntExist()) {
                $version->replicate()->forceFill([
                    'shared_from_version_id' => $version->id,
                    'approved_by' => auth()->id(),
                ])->save();
            }
        });
    }
}
