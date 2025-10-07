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
        // Process automated reminders every 5 minutes
        $schedule->call(function () {
            app(\App\Http\Controllers\AutomationController::class)->processDueReminders();
        })->everyFiveMinutes();

        // Check subscription expirations daily
        $schedule->call(function () {
            \App\Models\Subscription::where('status', 'active')
                ->where('expires_at', '<', now())
                ->update([
                    'status' => 'expired',
                    'remove_branding' => false
                ]);
        })->daily();

        // Clean up old logs monthly (optional)
        $schedule->call(function () {
            \App\Models\EmailLog::where('created_at', '<', now()->subMonths(6))->delete();
            \App\Models\SmsLog::where('created_at', '<', now()->subMonths(6))->delete();
        })->monthly();
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
