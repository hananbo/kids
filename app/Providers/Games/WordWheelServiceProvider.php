<?php

namespace App\Providers\Games;

use App\Games\GamePackage;
use App\Games\WordWheel\SeedWordWheel;
use App\Games\WordWheel\SelectWheelPuzzle;
use App\Games\WordWheel\WordWheelController;
use App\Http\Middleware\EnsureChildPlay;
use App\Http\Middleware\SetHebrewLocale;
use App\Models\ChildProfile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WordWheelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $binding = 'game.'.SelectWheelPuzzle::GAME;

        $this->app->singleton($binding, fn (): GamePackage => new GamePackage(
            key: SelectWheelPuzzle::GAME,
            name: 'גלגל המילים',
            entryRoute: 'word-wheel.play.show',
            poolLabel: 'מצב מאגר המילים',
            poolStatus: fn (ChildProfile $profile): string => ($this->app->make(SelectWheelPuzzle::class))($profile)
                ? 'יש מילים מאושרות שמתאימות לפרופיל.'
                : 'אין כרגע מילים מאושרות שמתאימות לפרופיל.',
        ));
        $this->app->tag($binding, GamePackage::class);
    }

    public function boot(): void
    {
        $this->commands([SeedWordWheel::class]);

        if ($this->app->routesAreCached()) {
            return;
        }

        Route::prefix('{current_team}/word-wheel')
            ->name('word-wheel.play.')
            ->middleware(['web', 'auth', 'verified', EnsureChildPlay::class, SetHebrewLocale::class])
            ->group(function (): void {
                Route::get('/', [WordWheelController::class, 'show'])->name('show');
                Route::post('/', [WordWheelController::class, 'start'])->name('start');
                Route::post('{attempt}/spin', [WordWheelController::class, 'spin'])->whereNumber('attempt')->name('spin');
                Route::post('{attempt}/guess', [WordWheelController::class, 'guess'])->whereNumber('attempt')->name('guess');
                Route::post('{attempt}/solve', [WordWheelController::class, 'solve'])->whereNumber('attempt')->name('solve');
                Route::post('{attempt}/finish', [WordWheelController::class, 'finish'])->whereNumber('attempt')->name('finish');
            });
    }
}
