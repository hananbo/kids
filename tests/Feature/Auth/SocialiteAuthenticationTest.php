<?php

use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('guests can be redirected to GitHub', function () {
    Socialite::fake('github');

    $response = $this->get(route('socialite.github.redirect'));

    $response->assertRedirect();
});

test('users can register and authenticate with GitHub', function () {
    Socialite::fake('github', SocialiteUser::fake([
        'id' => 'github-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]));

    $response = $this->get(route('socialite.github.callback'));

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam]));
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->personalTeam())->not->toBeNull();
    $this->assertModelExists(
        SocialAccount::query()
            ->whereBelongsTo($user)
            ->where('provider', 'github')
            ->where('provider_user_id', 'github-123')
            ->firstOrFail(),
    );
});

test('GitHub authentication links an existing user by verified email', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    Socialite::fake('github', SocialiteUser::fake([
        'id' => 'github-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]));

    $this->get(route('socialite.github.callback'))
        ->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam]));

    $this->assertAuthenticatedAs($user);
    expect(User::query()->where('email', 'ada@example.com')->count())->toBe(1)
        ->and($user->socialAccounts()->firstOrFail()->provider_user_id)->toBe('github-123');
});

test('GitHub authentication requires a verified primary email', function () {
    Socialite::fake('github', SocialiteUser::fake([
        'id' => 'github-123',
        'name' => 'Ada Lovelace',
        'email' => null,
    ]));

    $response = $this->from(route('login'))->get(route('socialite.github.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

test('users can cancel GitHub authentication', function () {
    $response = $this->get(route('socialite.github.callback', [
        'error' => 'access_denied',
    ]));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('socialite');
});
