<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Register your custom Artisan Commands.
     */
    protected $commands = [
        \App\Console\Commands\RunRecurringTransactions::class,
        \App\Console\Commands\RunSmartInsights::class, // ⬅️ tambahkan jika pakai smart-insights
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        /**
         * RECURRING TRANSACTIONS
         * - Kamu sebelumnya pakai dailyAt('00:10')
         * - Saya tambah sedikit perbaikan: withoutOverlapping() supaya aman tidak double-run
         */
        $schedule->command('recurring:run')
            ->dailyAt('00:10')
            ->withoutOverlapping();

        /**
         * SMART INSIGHTS (optional)
         * - Memberikan insight kenaikan pengeluaran (month-to-month)
         * - Jalan setiap tanggal 2 jam 08:00
         */
        $schedule->command('smart-insights:run')
            ->monthlyOn(2, '08:00')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
