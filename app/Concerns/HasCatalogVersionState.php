<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;

/** @phpstan-require-extends Model */
trait HasCatalogVersionState
{
    /** @param Builder<static> $query */
    #[Scope]
    protected function current(Builder $query, bool $shared = false): void
    {
        $table = $this->getTable();
        $owner = static::CATALOG_OWNER_KEY;

        $query->whereNull("{$table}.withdrawn_at")
            ->where("{$table}.id", '=', function (QueryBuilder $candidate) use ($table, $owner, $shared): void {
                $candidate->from("$table as candidate")
                    ->select('candidate.id')
                    ->whereColumn("candidate.$owner", "$table.$owner")
                    ->whereColumn('candidate.locale', "$table.locale")
                    ->whereNull('candidate.shared_from_version_id', not: $shared)
                    ->orderByDesc($shared ? 'candidate.shared_from_version_id' : 'candidate.id')
                    ->limit(1);
            });
    }

    /** @return HasOne<static, $this> */
    public function sharedCopy(): HasOne
    {
        return $this->hasOne(static::class, 'shared_from_version_id');
    }
}
