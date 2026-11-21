<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VerifyApplicationHealth
{
    /**
     * Create the event listener.
     */
    public function handle(DiagnosingHealth $event): void
    {
        if (config('security.health.check_database')) {
            DB::select('select 1');
        }

        if (config('security.health.check_cache')) {
            $key = 'health:'.Str::uuid();
            Cache::put($key, 'ok', 10);

            if (Cache::pull($key) !== 'ok') {
                throw new \RuntimeException('Cache health check failed.');
            }
        }
    }
}
