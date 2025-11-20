<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\NotificationService;
use App\Services\BudgetService;
use Carbon\Carbon;

class RunRecurringTransactions extends Command
{
    /**
     * Nama command yang dipakai di artisan.
     *
     * php artisan recurring:run
     */
    protected $signature = 'recurring:run';

    protected $description = 'Generate transactions + notifications from active recurring rules';

    public function handle()
    {
        $now = Carbon::now();

        $recurrings = RecurringTransaction::where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->get();

        if ($recurrings->isEmpty()) {
            $this->info('No recurring transactions due.');
            return Command::SUCCESS;
        }

        foreach ($recurrings as $rec) {
            // Kalau sudah lewat end_date → nonaktifkan
            if ($rec->end_date && Carbon::parse($rec->end_date)->lt($now->startOfDay())) {
                $rec->is_active = false;
                $rec->save();
                $this->info("Recurring #{$rec->id} expired, deactivated.");
                continue;
            }

            // 1) Buat transaksi beneran di tabel transactions
            $transaction = Transaction::create([
                'user_id'          => $rec->user_id,
                'bank_id'          => $rec->bank_id,
                'asset_id'         => $rec->asset_id,
                'category_id'      => $rec->category_id,
                // di sistem kamu amount selalu positif, income/expense dibedakan via category.type
                'amount'           => $rec->amount,
                'transaction_date' => $rec->next_run_at ?? $now,
                'description'      => '[RECURRING] '.$rec->name,
                'created_by'       => $rec->user_id,
                'deleted'          => false,
            ]);

            // 2) Notifikasi seperti TransactionController::store
            NotificationService::create(
                $rec->user_id,
                'transaction_created',
                'Transaksi Berulang',
                'Transaksi berulang "'.$rec->name.'" sebesar '
                    .number_format((float)$transaction->amount, 0, ',', '.')
                    .' berhasil dibuat otomatis.',
                ['transaction_id' => $transaction->id, 'category_id' => $transaction->category_id],
                'success',
                null
            );

            // 3) Evaluasi budget untuk kategori terkait
            BudgetService::onTransactionChanged($rec->user_id, (int)$transaction->category_id);

            // 4) Hitung next_run_at berikutnya
            $rec->next_run_at = $this->calculateNextRunAt($rec);
            $rec->save();

            $this->info("Generated transaction #{$transaction->id} from recurring #{$rec->id}");
        }

        return Command::SUCCESS;
    }

    /**
     * Hitung next_run_at berikutnya berdasarkan recurring rule.
     */
    protected function calculateNextRunAt(RecurringTransaction $rec): ?Carbon
    {
        if (!$rec->is_active) {
            return null;
        }

        $now  = Carbon::now();
        $base = $rec->next_run_at
            ? Carbon::parse($rec->next_run_at)->startOfDay()
            : (
                $rec->start_date
                    ? Carbon::parse($rec->start_date)->startOfDay()
                    : $now->copy()->startOfDay()
            );

        switch ($rec->frequency) {
            case 'daily':
                return $base->copy()->addDay()->startOfDay();

            case 'weekly':
                $dayOfWeek = $rec->day_of_week ?? $base->dayOfWeek;
                $next = $base->copy()->addWeek(); // minggu depan
                while ($next->dayOfWeek !== (int)$dayOfWeek) {
                    $next->addDay();
                }
                return $next->startOfDay();

            case 'monthly':
                $dayOfMonth = $rec->day_of_month ?? $base->day;
                $nextMonth  = $base->copy()->addMonthNoOverflow();
                $next = $nextMonth->day(
                    min($dayOfMonth, $nextMonth->daysInMonth)
                );
                return $next->startOfDay();

            case 'yearly':
                return $base->copy()->addYear()->startOfDay();

            default:
                return null;
        }
    }
}
