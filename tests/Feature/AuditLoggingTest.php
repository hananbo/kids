<?php

use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('security.audit.key', 'test-audit-key-that-is-longer-than-thirty-two-characters');
    config()->set('security.audit.log_channel', 'null');
});

test('successful and failed authentication attempts produce signed audit evidence', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->post(route('logout'))->assertRedirect();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'incorrect-password',
    ]);

    expect(AuditLog::query()->where('event', 'auth.login.succeeded')->firstOrFail()->hasValidSignature())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'auth.logout')->firstOrFail()->hasValidSignature())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'auth.login.failed')->firstOrFail()->hasValidSignature())->toBeTrue();
});

test('audit metadata is redacted and records are tamper evident and append only', function () {
    $user = User::factory()->create();

    $auditLog = app(AuditLogger::class)->record('security.control.tested', $user, $user, [
        'safe' => 'retained',
        'password' => 'must-not-be-stored',
        'nested' => ['api_token' => 'must-not-be-stored'],
    ]);

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->metadata)->toMatchArray([
            'safe' => 'retained',
            'password' => '[REDACTED]',
            'nested' => ['api_token' => '[REDACTED]'],
        ])
        ->and($auditLog->hasValidSignature())->toBeTrue();

    expect(fn () => $auditLog->update(['event' => 'tampered']))
        ->toThrow(LogicException::class, 'append-only');

    DB::table('audit_logs')->where('id', $auditLog->id)->update(['event' => 'tampered']);

    expect($auditLog->fresh()->hasValidSignature())->toBeFalse();
});

test('privilege changes produce actor and subject audit evidence', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner)->patch(route('teams.members.update', [$team, $member]), [
        'role' => TeamRole::Admin->value,
    ])->assertRedirect();

    $auditLog = AuditLog::query()->where('event', 'access.member.role_changed')->firstOrFail();

    expect($auditLog->actor_id)->toBe((string) $owner->id)
        ->and($auditLog->subject_id)->toBe((string) $team->id)
        ->and($auditLog->metadata)->toMatchArray([
            'member_id' => $member->id,
            'previous_role' => TeamRole::Member->value,
            'new_role' => TeamRole::Admin->value,
        ])
        ->and($auditLog->hasValidSignature())->toBeTrue();
});

test('audit verifier fails when stored evidence has been altered', function () {
    $auditLog = app(AuditLogger::class)->record('security.control.tested');

    $this->artisan('audit:verify --json')
        ->expectsOutputToContain('"invalid_records": 0')
        ->assertSuccessful();

    DB::table('audit_logs')->where('id', $auditLog->id)->update(['event' => 'tampered']);

    $this->artisan('audit:verify --json')
        ->expectsOutputToContain('"invalid_records": 1')
        ->assertFailed();
});
