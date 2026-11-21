<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Throwable;

#[Signature('security:check {--json : Output machine-readable evidence}')]
#[Description('Validate production security configuration and operational evidence prerequisites')]
class SecurityCheck extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checks = $this->checks();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'generated_at' => now()->toISOString(),
                'environment' => app()->environment(),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Status', 'Control', 'Evidence'],
                array_map(fn (array $check): array => [
                    strtoupper($check['status']),
                    $check['control'],
                    $check['evidence'],
                ], $checks),
            );
        }

        return collect($checks)->contains('status', 'fail') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array{status: 'pass'|'warn'|'fail', control: string, evidence: string}>
     */
    private function checks(): array
    {
        $auditKey = (string) config('security.audit.key');
        $appKey = (string) config('app.key');

        return [
            $this->check(app()->isProduction(), 'production_environment', 'APP_ENV must be production.'),
            $this->check(! config('app.debug'), 'debug_disabled', 'APP_DEBUG must be false.'),
            $this->check(str_starts_with((string) config('app.url'), 'https://'), 'https_url', 'APP_URL must use HTTPS.'),
            $this->check($this->hasValidApplicationKey($appKey), 'application_key', 'APP_KEY must contain enough random bytes for the configured cipher.'),
            $this->check(config('session.encrypt') === true, 'session_encryption', 'SESSION_ENCRYPT must be true.'),
            $this->check(config('session.secure') === true, 'secure_session_cookie', 'SESSION_SECURE_COOKIE must be true.'),
            $this->check(config('session.http_only') === true, 'http_only_session_cookie', 'SESSION_HTTP_ONLY must be true.'),
            $this->check(in_array(config('session.driver'), ['database', 'redis'], true), 'server_side_sessions', 'Use database or Redis sessions.'),
            $this->check(config('security.http.trusted_hosts') !== [], 'trusted_hosts', 'TRUSTED_HOSTS must explicitly list production hosts.'),
            $this->check(
                config('security.http.trusted_proxies') !== []
                    && ! in_array('*', config('security.http.trusted_proxies'), true),
                'trusted_proxies',
                'TRUSTED_PROXIES must explicitly list load balancer addresses; wildcard trust is prohibited.',
            ),
            $this->check(strlen($auditKey) >= 32 && $auditKey !== $appKey, 'dedicated_audit_key', 'AUDIT_LOG_KEY must be at least 32 characters and distinct from APP_KEY.'),
            $this->check(config('security.audit.fail_closed') === true, 'audit_fail_closed', 'AUDIT_LOG_FAIL_CLOSED must be true.'),
            $this->check((int) config('security.audit.retention_days') >= 365, 'audit_retention', 'Retain audit records for at least 365 days or the longer approved policy period.'),
            $this->check(
                config('logging.channels.'.config('security.audit.log_channel')) !== null
                    && ! in_array(config('security.audit.log_channel'), ['null', 'single', 'daily', 'emergency'], true),
                'external_audit_channel',
                'AUDIT_LOG_CHANNEL must use a configured external or process-output channel.',
            ),
            $this->check(
                config('logging.channels.single.level') !== 'debug'
                    && config('logging.channels.daily.level') !== 'debug',
                'production_log_level',
                'LOG_LEVEL must not be debug.',
            ),
            $this->check(
                $this->hasExternalLogDestination(),
                'external_application_logs',
                'LOG_CHANNEL/LOG_STACK must include stderr, syslog, errorlog, papertrail, or slack.',
            ),
            $this->check(! in_array(config('queue.default'), ['sync', 'null'], true), 'durable_queue', 'Use a durable queue backend.'),
            $this->warning(config('database.default') !== 'sqlite', 'production_database', 'Use a managed production database rather than SQLite.'),
            $this->warning(config('mail.default') !== 'log', 'transactional_mail', 'Configure a production mail transport.'),
            $this->assuranceCheck('backup_provider', 'backup_provider', 0),
            $this->assuranceCheck('backup_restore_tested_at', 'recent_restore_test', 90),
            $this->assuranceCheck('incident_response_tested_at', 'recent_incident_response_test', 365),
        ];
    }

    /**
     * @return array{status: 'pass'|'fail', control: string, evidence: string}
     */
    private function check(bool $passes, string $control, string $evidence): array
    {
        return [
            'status' => $passes ? 'pass' : 'fail',
            'control' => $control,
            'evidence' => $passes ? 'Configured.' : $evidence,
        ];
    }

    /**
     * @return array{status: 'pass'|'warn', control: string, evidence: string}
     */
    private function warning(bool $passes, string $control, string $evidence): array
    {
        return [
            'status' => $passes ? 'pass' : 'warn',
            'control' => $control,
            'evidence' => $passes ? 'Configured.' : $evidence,
        ];
    }

    private function hasValidApplicationKey(string $key): bool
    {
        if (str_starts_with($key, 'base64:')) {
            $decodedKey = base64_decode(substr($key, 7), true);

            if ($decodedKey === false) {
                return false;
            }

            $key = $decodedKey;
        }

        return Encrypter::supported($key, (string) config('app.cipher'));
    }

    private function hasExternalLogDestination(): bool
    {
        $channel = (string) config('logging.default');
        $channels = $channel === 'stack'
            ? config('logging.channels.stack.channels', [])
            : [$channel];

        if (! is_array($channels)) {
            return false;
        }

        foreach ($channels as $configuredChannel) {
            if (is_string($configuredChannel) && in_array(
                $configuredChannel,
                ['stderr', 'syslog', 'errorlog', 'papertrail', 'slack'],
                true,
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{status: 'pass'|'warn', control: string, evidence: string}
     */
    private function assuranceCheck(string $key, string $control, int $maximumAgeDays): array
    {
        $value = config("security.assurance.{$key}");

        if (! is_string($value) || $value === '') {
            return [
                'status' => 'warn',
                'control' => $control,
                'evidence' => "security.assurance.{$key} has not been supplied by an owner.",
            ];
        }

        if ($maximumAgeDays === 0) {
            return ['status' => 'pass', 'control' => $control, 'evidence' => $value];
        }

        try {
            $testedAt = CarbonImmutable::parse($value);
        } catch (Throwable) {
            return ['status' => 'warn', 'control' => $control, 'evidence' => 'Evidence date is invalid.'];
        }

        $isCurrent = $testedAt->betweenIncluded(now()->subDays($maximumAgeDays), now());

        return [
            'status' => $isCurrent ? 'pass' : 'warn',
            'control' => $control,
            'evidence' => $isCurrent ? $testedAt->toDateString() : "Evidence is older than {$maximumAgeDays} days.",
        ];
    }
}
