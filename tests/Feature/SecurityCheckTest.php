<?php

use Illuminate\Support\Carbon;

test('production configuration passes mandatory security checks', function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
    $this->app->detectEnvironment(fn (): string => 'production');

    config()->set([
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://app.example.com',
        'app.key' => 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
        'session.encrypt' => true,
        'session.secure' => true,
        'session.http_only' => true,
        'session.driver' => 'database',
        'security.http.trusted_hosts' => ['^app\\.example\\.com$'],
        'security.http.trusted_proxies' => ['10.0.0.0/8'],
        'security.audit.key' => 'dedicated-audit-key-that-is-over-thirty-two-characters',
        'security.audit.fail_closed' => true,
        'security.audit.log_channel' => 'stderr',
        'logging.channels.single.level' => 'info',
        'logging.channels.daily.level' => 'info',
        'logging.channels.stack.channels' => ['stderr'],
        'queue.default' => 'database',
        'security.assurance.backup_provider' => 'managed-postgresql',
        'security.assurance.backup_restore_tested_at' => '2026-07-01',
        'security.assurance.incident_response_tested_at' => '2026-01-15',
    ]);

    $this->artisan('security:check --json')
        ->expectsOutputToContain('"production_environment"')
        ->assertSuccessful();
});

test('unsafe production configuration fails the readiness gate', function () {
    $this->app->detectEnvironment(fn (): string => 'production');
    config()->set([
        'app.env' => 'production',
        'app.debug' => true,
        'app.url' => 'http://app.example.com',
        'session.encrypt' => false,
        'session.secure' => false,
        'security.http.trusted_hosts' => [],
        'security.http.trusted_proxies' => [],
    ]);

    $this->artisan('security:check --json')
        ->expectsOutputToContain('"debug_disabled"')
        ->assertFailed();
});
