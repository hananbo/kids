<?php

namespace App\Games;

use App\Models\ChildProfile;
use Closure;

final readonly class GamePackage
{
    /** @param Closure(ChildProfile): string $poolStatus */
    public function __construct(
        public string $key,
        public string $name,
        public string $entryRoute,
        public string $poolLabel,
        public Closure $poolStatus,
    ) {}
}
