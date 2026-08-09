<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Suspend overdue tenant subscriptions on the 5th of each month at 00:05
        $schedule->command('subscription:suspend-overdue')->monthlyOn(5, '00:05');

        // Detect tenants that exceeded their max_users quota (pricing / seats)
        $schedule->command('tenants:refresh-metrics')->everyFifteenMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
