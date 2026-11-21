<?php

namespace App\Games;

use App\Http\Middleware\EnsureChildPlay;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Support\Facades\Route;

class GamePackages
{
    /** @param iterable<GamePackage> $packages */
    public function __construct(
        #[Tag(GamePackage::class)]
        private iterable $packages,
    ) {}

    /** @return array<string, GamePackage> */
    public function all(): array
    {
        $installed = [];

        foreach ($this->packages as $package) {
            $route = Route::getRoutes()->getByName($package->entryRoute);

            if ($route && EnsureChildPlay::marks($route)) {
                $installed[$package->key] = $package;
            }
        }

        return $installed;
    }
}
