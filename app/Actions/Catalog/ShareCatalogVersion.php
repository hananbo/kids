<?php

namespace App\Actions\Catalog;

use App\Models\PublishedQuestionVersion;
use App\Models\PublishedWordEntryVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ShareCatalogVersion
{
    public function __invoke(PublishedQuestionVersion|PublishedWordEntryVersion $version): void
    {
        Gate::authorize('manage-shared-catalog');

        DB::transaction(function () use ($version): void {
            $version = $version->newQuery()
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
