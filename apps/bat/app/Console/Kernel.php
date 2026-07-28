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
        $log = storage_path('logs/scheduled-tasks.log');

        $schedule->command('quotes:check-expired')
            ->dailyAt('07:30')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // Mark overdue invoices and trigger dunning — every morning at 07:00.
        $schedule->command('invoices:check-overdue')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // Auto-generate preventive maintenance orders from active contracts — daily at 06:00.
        $schedule->command('maintenance:generate-preventive')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // Warn about imminent SLA breaches (orders due within 2h) — every hour.
        $schedule->command('maintenance:check-sla')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);
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
