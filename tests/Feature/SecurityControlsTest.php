<?php

use App\Models\User;
use Illuminate\Support\Facades\Vite;
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

test('only a local loopback Vite server can supply development styles and modules', function (string $environment, string $origin, bool $allowed, string $client = '127.0.0.1') {
    app()->detectEnvironment(fn (): string => $environment);
    $assetServer = tempnam(sys_get_temp_dir(), 'quiz-vite');
    file_put_contents($assetServer, $origin);
    $oldFile = Vite::hotFile();
    try {
        Vite::useHotFile($assetServer);
        $policy = $this->withServerVariables(['REMOTE_ADDR' => $client])->get('/up')->headers->get('Content-Security-Policy');
        foreach (['style-src', 'script-src', 'connect-src'] as $directive) {
            preg_match('/(?:^|; )'.preg_quote($directive, '/').' ([^;]+)/', $policy, $found);
            expect(str_contains($found[1], $origin))->toBe($allowed);
        }
    } finally {
        Vite::useHotFile($oldFile);
        unlink($assetServer);
    }
})->with([
    ['local', 'http://127.0.0.1:5173', true],
    ['local', 'http://localhost.attacker.test:5173', false],
    ['production', 'http://localhost:5173', false],
]);

test('remote browsers use built assets while a local loopback dev server is running', function () {
    app()->detectEnvironment(fn (): string => 'local');
    $marker = tempnam(sys_get_temp_dir(), 'quiz-vite');
    $old = Vite::hotFile();
    file_put_contents($marker, 'http://127.0.0.1:5173');
    try {
        Vite::useHotFile($marker);
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.15'])->get('/login')
            ->assertOk()->assertDontSee('127.0.0.1:5173')->assertSee('/build/assets/', false);
        expect(Vite::hotFile())->toBe($marker);
    } finally {
        Vite::useHotFile($old);
        unlink($marker);
    }
});
