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
                Route::get('{run}/questions/{question}/media/{slot}', [QuizController::class, 'media'])
                    ->whereNumber(['run', 'question'])
                    ->whereIn('slot', ['prompt', '0', '1', '2', '3'])
                    ->name('media');
                Route::post('{run}/hint', [QuizController::class, 'hint'])->whereNumber('run')->name('hint');
                Route::post('{run}/advance', [QuizController::class, 'advance'])->whereNumber('run')->name('advance');
            });
    }
}
