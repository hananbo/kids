<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $event_id
 * @property string $event
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $request_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed> $metadata
 * @property string $signature
 * @property Carbon $occurred_at
 */
#[Fillable([
    'event_id', 'event', 'actor_type', 'actor_id', 'subject_type', 'subject_id',
    'request_id', 'ip_address', 'user_agent', 'metadata', 'signature', 'occurred_at',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Determine whether the record still matches its HMAC signature.
     */
    public function hasValidSignature(): bool
    {
        $keys = [
            (string) config('security.audit.key'),
            ...config('security.audit.previous_keys', []),
        ];

        return collect($keys)->contains(
            fn (string $key): bool => hash_equals($this->signature, $this->calculateSignature($key)),
        );
    }

    /**
     * Calculate the integrity signature for the current record values.
     */
    public function calculateSignature(?string $key = null): string
    {
        $payload = json_encode([
            'event_id' => $this->event_id,
            'event' => $this->event,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'request_id' => $this->request_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $this->canonicalize($this->metadata),
            'occurred_at' => $this->occurred_at->toISOString(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $payload, $key ?? (string) config('security.audit.key'));
    }

    /**
     * Produce stable key ordering across database JSON implementations.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function canonicalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }

    /**
     * Prevent application code from altering or deleting audit evidence.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit records are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit records are append-only.'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
