<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AuditLogger
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'authorization', 'cookie', 'credential', 'password', 'recovery_code', 'secret', 'token',
    ];

    /**
     * Record a security-relevant event without persisting secrets.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $event,
        ?Authenticatable $actor = null,
        Model|Authenticatable|null $subject = null,
        array $metadata = [],
    ): ?AuditLog {
        $request = app()->bound('request') ? request() : null;
        $auditLog = new AuditLog([
            'event_id' => (string) Str::uuid(),
            'event' => $event,
            'actor_type' => $actor ? $actor::class : null,
            'actor_id' => $actor ? (string) $actor->getAuthIdentifier() : null,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $this->subjectIdentifier($subject),
            'request_id' => $request instanceof Request ? $request->attributes->get('request_id') : null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? Str::limit((string) $request->userAgent(), 1024, '') : null,
            'metadata' => $this->sanitize($metadata),
            'occurred_at' => now(),
        ]);
        $auditLog->signature = $auditLog->calculateSignature();

        try {
            $auditLog->save();

            Log::channel((string) config('security.audit.log_channel'))->notice('security.audit', [
                'event_id' => $auditLog->event_id,
                'event' => $auditLog->event,
                'actor_id' => $auditLog->actor_id,
                'subject_type' => $auditLog->subject_type,
                'subject_id' => $auditLog->subject_id,
                'request_id' => $auditLog->request_id,
                'signature' => $auditLog->signature,
            ]);

            return $auditLog;
        } catch (Throwable $exception) {
            Log::emergency('Security audit persistence failed.', [
                'event' => $event,
                'request_id' => $request instanceof Request ? $request->attributes->get('request_id') : null,
                'exception' => $exception,
            ]);

            if (config('security.audit.fail_closed')) {
                throw $exception;
            }

            return null;
        }
    }

    private function subjectIdentifier(Model|Authenticatable|null $subject): ?string
    {
        if ($subject instanceof Authenticatable) {
            return (string) $subject->getAuthIdentifier();
        }

        return $subject instanceof Model ? (string) $subject->getKey() : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitize(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $metadata[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $metadata[$key] = $this->sanitize($value);
            } elseif (is_object($value)) {
                $metadata[$key] = '[OBJECT]';
            }
        }

        return $metadata;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = Str::lower($key);

        return collect(self::SENSITIVE_KEYS)->contains(
            fn (string $sensitiveKey): bool => str_contains($normalizedKey, $sensitiveKey),
        );
    }
}
