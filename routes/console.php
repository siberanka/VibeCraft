<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| One cPanel cron entry drives everything:
|   * * * * * /usr/local/bin/php /home/USER/vibecraft/artisan schedule:run >> /dev/null 2>&1
| Hosts that only allow 5-minute cron still work; jobs simply run less often.
*/

Schedule::call(fn () => Cache::forever('vibecraft.cron.last_run', now()->toIso8601String()))->name('cron-heartbeat')->everyMinute();

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);
Schedule::command('vibecraft:status-refresh')->everyTwoMinutes()->withoutOverlapping(5);
Schedule::command('vibecraft:expire')->everyMinute()->withoutOverlapping(5);
Schedule::command('vibecraft:webhooks-retry')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('vibecraft:contract-notices')->everyMinute()->withoutOverlapping(5);
Schedule::command('vibecraft:prune')->hourly();
Schedule::command('vibecraft:ledger-reconcile')->dailyAt('04:10');
Schedule::command('vibecraft:cloudflare-ips')->weekly()->when(fn () => config('vibecraft.proxy.mode') === 'cloudflare');
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('auth:clear-resets')->hourly();
