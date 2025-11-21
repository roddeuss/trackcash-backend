<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Services\SmartInsightService;

class RunSmartInsights extends Command
{
    /**
     * php artisan smart-insights:run
     */
    protected $signature = 'smart-insights:run';

    protected $description = 'Generate smart spending insights & notifications for users';

    public function handle()
    {
        // Ambil semua user_id yang pernah punya transaksi
        $userIds = Transaction::select('user_id')
            ->distinct()
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->info('No users with transactions. Nothing to process.');
            return Command::SUCCESS;
        }

        $this->info('Running smart insights for '.count($userIds).' users...');

        foreach ($userIds as $userId) {
            $insight = SmartInsightService::checkMonthlySpendingChange(
                (int) $userId,
                20.0,   // threshold (20% atau bisa kamu ganti)
                true    // fireNotification = true (karena ini dari scheduler)
            );

            if ($insight) {
                $this->info("User #{$userId}: change {$insight['percent_change']}% ({$insight['direction']}).");
            } else {
                $this->line("User #{$userId}: no significant change.");
            }
        }

        $this->info('Smart insights processing done.');
        return Command::SUCCESS;
    }
}
