<?php

return [
    'http' => [
        'trusted_hosts' => array_values(array_filter(explode(',', (string) env('TRUSTED_HOSTS', '')))),
        'trusted_proxies' => array_values(array_filter(explode(',', (string) env('TRUSTED_PROXIES', '')))),
        'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'csp_report_only' => (bool) env('CSP_REPORT_ONLY', false),
    ],

    'audit' => [
        'key' => env('AUDIT_LOG_KEY', env('APP_KEY')),
        'previous_keys' => array_values(array_filter(explode(',', (string) env('AUDIT_LOG_PREVIOUS_KEYS', '')))),
        'log_channel' => env('AUDIT_LOG_CHANNEL', 'stderr'),
        'fail_closed' => (bool) env('AUDIT_LOG_FAIL_CLOSED', true),
        'retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 400),
    ],

    'health' => [
        'check_database' => (bool) env('HEALTH_CHECK_DATABASE', true),
        'check_cache' => (bool) env('HEALTH_CHECK_CACHE', true),
    ],

    'assurance' => [
        'backup_provider' => env('BACKUP_PROVIDER'),
        'backup_restore_tested_at' => env('BACKUP_RESTORE_TESTED_AT'),
        'incident_response_tested_at' => env('INCIDENT_RESPONSE_TESTED_AT'),
    ],
];
