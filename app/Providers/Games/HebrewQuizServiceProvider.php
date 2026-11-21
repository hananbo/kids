<?php

namespace App\Providers\Games;

use App\Games\HebrewQuiz\QuizController;
use App\Http\Middleware\SetHebrewLocale;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HebrewQuizServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::prefix('{current_team}/quiz')
            ->name('quiz.play.')
            ->middleware(['web', 'auth', 'verified', SetHebrewLocale::class])
            ->group(function (): void {
                Route::get('/', [QuizController::class, 'show'])->name('show');
                Route::post('/', [QuizController::class, 'start'])->name('start');
                Route::post('{run}/answer', [QuizController::class, 'answer'])->whereNumber('run')->name('answer');
                Route::post('{run}/advance', [QuizController::class, 'advance'])->whereNumber('run')->name('advance');
            });
    }
}
