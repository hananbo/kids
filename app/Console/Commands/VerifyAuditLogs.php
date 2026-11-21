<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('audit:verify {--from= : Verify records on or after this ISO-8601 date} {--json : Output machine-readable evidence}')]
#[Description('Verify the integrity signatures of append-only security audit records')]
class VerifyAuditLogs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = AuditLog::query()->orderBy('id');
        $from = $this->option('from');

        if (is_string($from) && $from !== '') {
            try {
                $query->where('occurred_at', '>=', CarbonImmutable::parse($from));
            } catch (Throwable) {
                $this->error('The --from value must be a valid ISO-8601 date.');

                return self::INVALID;
            }
        }

        $checked = 0;
        $invalidEventIds = [];
        $firstOccurredAt = null;
        $lastOccurredAt = null;

        $query->chunkById(500, function ($auditLogs) use (&$checked, &$invalidEventIds, &$firstOccurredAt, &$lastOccurredAt): void {
            foreach ($auditLogs as $auditLog) {
                $checked++;
                $firstOccurredAt ??= $auditLog->occurred_at;
                $lastOccurredAt = $auditLog->occurred_at;

                if (! $auditLog->hasValidSignature()) {
                    $invalidEventIds[] = $auditLog->event_id;
                }
            }
        });

        $evidence = [
            'generated_at' => now()->toISOString(),
            'from' => is_string($from) && $from !== '' ? $from : null,
            'records_checked' => $checked,
            'invalid_records' => count($invalidEventIds),
            'invalid_event_ids' => $invalidEventIds,
            'first_occurred_at' => $firstOccurredAt?->toISOString(),
            'last_occurred_at' => $lastOccurredAt?->toISOString(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Evidence', 'Value'], collect($evidence)->map(
                fn (mixed $value, string $key): array => [$key, is_array($value) ? implode(', ', $value) : ($value ?? 'none')],
            )->values()->all());
        }

        return $invalidEventIds === [] ? self::SUCCESS : self::FAILURE;
    }
}
