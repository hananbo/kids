<?php

namespace App\Listeners;

use App\Support\AuditLogger;
use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Events\PasskeyVerified;

class RecordAuthenticationEvent
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Handle the event.
     */
    public function handle(
        CurrentDeviceLogout|Failed|Lockout|Login|Logout|OtherDeviceLogout|PasskeyDeleted|PasskeyRegistered|PasskeyVerified|PasswordReset|PasswordResetLinkSent|RecoveryCodeReplaced|RecoveryCodesGenerated|Registered|TwoFactorAuthenticationConfirmed|TwoFactorAuthenticationDisabled|TwoFactorAuthenticationEnabled|TwoFactorAuthenticationFailed|Verified $event,
    ): void {
        $actor = property_exists($event, 'user') && $event->user instanceof Authenticatable
            ? $event->user
            : null;

        $this->auditLogger->record(
            event: match ($event::class) {
                Registered::class => 'auth.registered',
                Login::class => 'auth.login.succeeded',
                Failed::class => 'auth.login.failed',
                Logout::class => 'auth.logout',
                CurrentDeviceLogout::class => 'auth.device.current_logged_out',
                OtherDeviceLogout::class => 'auth.device.other_logged_out',
                Lockout::class => 'auth.login.locked_out',
                PasswordReset::class => 'auth.password.reset',
                PasswordResetLinkSent::class => 'auth.password.reset_link_sent',
                Verified::class => 'auth.email.verified',
                TwoFactorAuthenticationEnabled::class => 'auth.two_factor.enabled',
                TwoFactorAuthenticationConfirmed::class => 'auth.two_factor.confirmed',
                TwoFactorAuthenticationDisabled::class => 'auth.two_factor.disabled',
                TwoFactorAuthenticationFailed::class => 'auth.two_factor.failed',
                RecoveryCodesGenerated::class => 'auth.two_factor.recovery_codes_generated',
                RecoveryCodeReplaced::class => 'auth.two_factor.recovery_code_used',
                PasskeyRegistered::class => 'auth.passkey.registered',
                PasskeyDeleted::class => 'auth.passkey.deleted',
                PasskeyVerified::class => 'auth.passkey.verified',
                default => throw new \LogicException('Unsupported authentication event: '.$event::class),
            },
            actor: $actor,
            subject: $actor,
            metadata: [
                'guard' => property_exists($event, 'guard') ? $event->guard : null,
                'remember' => $event instanceof Login ? $event->remember : null,
                'passkey_id' => property_exists($event, 'passkey') ? $event->passkey->getKey() : null,
            ],
        );
    }
}
