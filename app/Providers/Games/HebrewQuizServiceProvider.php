<?php

namespace App\Providers\Games;

use App\Games\GamePackage;
use App\Games\HebrewQuiz\QuizController;
use App\Games\HebrewQuiz\SeedHebrewQuiz;
use App\Games\HebrewQuiz\SelectQuizQuestions;
use App\Http\Middleware\EnsureChildPlay;
use App\Http\Middleware\SetHebrewLocale;
use App\Models\ChildProfile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HebrewQuizServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $binding = 'game.'.SelectQuizQuestions::GAME;

        $this->app->singleton($binding, fn (): GamePackage => new GamePackage(
            key: SelectQuizQuestions::GAME,
            name: 'חידון הניצוצות',
            entryRoute: 'quiz.play.show',
            poolLabel: 'מצב מאגר השאלות',
            poolStatus: function (ChildProfile $profile): string {
                $shortages = $this->app->make(SelectQuizQuestions::class)->poolShortages($profile);

                return $shortages === []
                    ? 'יש מספיק שאלות מאושרות לפתיחת משחק ברמה הזמינה.'
                    : implode(' ', $shortages);
            },
        ));
        $this->app->tag($binding, GamePackage::class);
    }

    public function boot(): void
    {
        $this->commands([SeedHebrewQuiz::class]);

        if ($this->app->routesAreCached()) {
            return;
        }

        Route::prefix('{current_team}/quiz')
            ->name('quiz.play.')
            ->middleware(['web', 'auth', 'verified', EnsureChildPlay::class, SetHebrewLocale::class])
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
