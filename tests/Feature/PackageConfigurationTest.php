<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Billable;

test('first party packages are configured with safe application defaults', function () {
    expect(class_uses_recursive(User::class))
        ->toContain(Billable::class)
        ->and(config('ai.default'))->toBe('openai')
        ->and(config('cashier.currency'))->toBe('usd')
        ->and(config('pennant.default'))->toBe('database')
        ->and(config('horizon.defaults.supervisor-1.connection'))->toBe('redis')
        ->and(config('horizon.defaults.supervisor-1.tries'))->toBe(3)
        ->and(config('reverb.apps.apps.0.allowed_origins'))->not->toContain('*');

    $cashierWebhook = Route::getRoutes()->getByName('cashier.webhook');

    expect($cashierWebhook)
        ->not->toBeNull()
        ->and($cashierWebhook?->uri())->toBe('stripe/webhook')
        ->and($cashierWebhook?->methods())->toContain('POST');
});

test('first party package database schema is installed', function () {
    expect(Schema::hasColumns('users', ['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']))
        ->toBeTrue()
        ->and(Schema::hasTable('subscriptions'))->toBeTrue()
        ->and(Schema::hasTable('subscription_items'))->toBeTrue()
        ->and(Schema::hasTable('features'))->toBeTrue()
        ->and(Schema::hasTable('agent_conversations'))->toBeTrue()
        ->and(Schema::hasTable('agent_conversation_messages'))->toBeTrue()
        ->and(Schema::hasTable('telescope_entries'))->toBeTrue();
});
