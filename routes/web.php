<?php

use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\ChildModeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('parent-unlock', ChildModeController::class)
    ->middleware(['auth', 'password.confirm'])
    ->name('child-mode.exit');

Route::middleware(['guest', 'throttle:6,1'])->group(function () {
    Route::get('auth/github/redirect', [SocialiteController::class, 'redirect'])->name('socialite.github.redirect');
    Route::get('auth/github/callback', [SocialiteController::class, 'callback'])->name('socialite.github.callback');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
