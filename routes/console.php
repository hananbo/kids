<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;
use Laravel\Telescope\TelescopeServiceProvider;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()
    ->name('team-invitations:prune-expired')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Delete expired team invitations');

Schedule::command('audit:verify --json')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Verify security audit log integrity');

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->onOneServer()
    ->description('Capture Horizon queue metrics');

if (class_exists(TelescopeServiceProvider::class)) {
    Schedule::command('telescope:prune --hours=48')
        ->daily()
        ->onOneServer()
        ->description('Prune old Telescope entries');
}
