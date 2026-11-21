<?php

use App\Models\User;
use Illuminate\Support\Str;

test('responses include correlation and defensive browser headers', function () {
    $response = $this->get('https://localhost/');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("script-src 'self' 'nonce-");
    expect($response->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains');
    expect(Str::isUuid($response->headers->get('X-Request-ID')))->toBeTrue();
});

test('authenticated responses cannot be stored by intermediary caches', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $response->assertHeader('Pragma', 'no-cache');
});

test('health check validates application dependencies', function () {
    $this->get('/up')->assertOk();
});
