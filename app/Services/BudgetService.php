<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Notification;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Helpers\BudgetHelper;

class BudgetService
{
    /** Ambang notifikasi default (dalam persen) */
    public const DEFAULT_THRESHOLD = 80;

    /**
     * Evaluasi SEMUA budget milik user.
     * @return array daftar payload hasil evaluasi (tiap budget berisi spent, progress, dst)
     */
    public static function evaluateForUser(int $userId, ?int $threshold = null): array
    {
        $threshold = $threshold ?? (int) (config('app.budget_threshold', self::DEFAULT_THRESHOLD));

        $budgets = Budget::with('category')
            ->where('user_id', $userId)
            ->where('deleted', false)
            ->get();

        $results = [];
        foreach ($budgets as $b) {
            $calc = self::compute($b);
            $results[] = ['budget' => $b, 'calc' => $calc];
            self::maybeNotify($userId, $b, $calc, $threshold);
        }
        return $results;
    }

    /**
     * Evaluasi budget milik user TERKAIT 1 kategori.
     * Berguna dipanggil saat transaksi terjadi.
     */
    public static function evaluateForCategory(int $userId, int $categoryId, ?int $threshold = null): array
    {
        $threshold = $threshold ?? (int) (config('app.budget_threshold', self::DEFAULT_THRESHOLD));

        $budgets = Budget::with('category')
            ->where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('deleted', false)
            ->get();

        $results = [];
        foreach ($budgets as $b) {
            $calc = self::compute($b);
            $results[] = ['budget' => $b, 'calc' => $calc];
            self::maybeNotify($userId, $b, $calc, $threshold);
        }
        return $results;
    }

    /**
     * Evaluasi SATU budget (utility jika butuh dipakai manual).
     */
    public static function evaluateOne(Budget $budget, ?int $threshold = null): array
    {
        $threshold = $threshold ?? (int) (config('app.budget_threshold', self::DEFAULT_THRESHOLD));
        $calc = self::compute($budget);
        self::maybeNotify($budget->user_id, $budget, $calc, $threshold);
        return $calc;
    }

    /**
     * Hitung spent / remaining / progress + window menggunakan BudgetHelper.
     */
    private static function compute(Budget $b): array
    {
        [$start, $end] = BudgetHelper::getBudgetWindow($b);

        // hanya hitung pengeluaran kategori itu dalam window
        $spent = (float) Transaction::query()
            ->where('transactions.user_id', $b->user_id)
            ->where('transactions.deleted', false)
            ->where('transactions.category_id', $b->category_id)
            ->whereBetween('transactions.transaction_date', [$start, $end])
            ->select(DB::raw('SUM(ABS(transactions.amount)) as total'))
            ->value('total');

        $spent     = round($spent ?: 0, 2);
        $amount    = (float) $b->amount;
        $remaining = round(max(0, $amount - $spent), 2);
        $progress  = $amount > 0 ? round(min(100, ($spent / $amount) * 100), 2) : 0.0;

        return [
            'spent'     => $spent,
            'remaining' => $remaining,
            'progress'  => $progress,
            'amount'    => $amount,
            'window'    => [
                'start' => $start->copy(),
                'end'   => $end->copy(),
            ],
        ];
    }

    /**
     * Buat notifikasi jika progress melewati threshold.
     * Hindari duplikasi: cek notification dengan type "budget_threshold"
     * untuk budget_id yang sama di dalam window saat ini & belum dihapus.
     */
    private static function maybeNotify(int $userId, Budget $b, array $calc, int $threshold): ?Notification
    {
        $progress = (float) ($calc['progress'] ?? 0);
        if ($progress < $threshold) {
            return null;
        }

        /** @var Carbon $start */
        /** @var Carbon $end */
        $start = $calc['window']['start'];
        $end   = $calc['window']['end'];

        // Cegah notifikasi berulang dalam periode window yang sama
        $existing = Notification::query()
            ->where('user_id', $userId)
            ->where('type', 'budget_threshold')
            ->where('deleted', false)
            // simpan notifikasi yang dibuat dalam window ini (created_at di antara window)
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            // data->budget_id == $b->id
            ->where(function ($q) use ($b) {
                $q->where('data->budget_id', $b->id)
                  ->orWhereNull('data'); // jaga-jaga kalau data null (legacy)
            })
            ->first();

        if ($existing) {
            return null; // sudah ada notifikasi untuk window ini
        }

        // Compose message
        $msg = sprintf(
            'Budget "%s" (kategori: %s) sudah mencapai %.2f%% dari total %s.',
            $b->name ?: '-',
            optional($b->category)->name ?: '-',
            $progress,
            number_format((float) $calc['amount'], 0, ',', '.')
        );

        return NotificationService::create(
            userId:    $userId,
            type:      'budget_threshold',
            title:     'Budget Hampir Habis',
            message:   $msg,
            data: [
                'budget_id'    => $b->id,
                'category_id'  => $b->category_id,
                'progress'     => $progress,
                'threshold'    => $threshold,
                'spent'        => $calc['spent'],
                'amount'       => $calc['amount'],
                'window_start' => $start->toDateTimeString(),
                'window_end'   => $end->toDateTimeString(),
            ],
            severity:  'warning',
            actionUrl: null
        );
    }

    /**
     * Hook sederhana yang dipanggil saat transaksi berubah.
     * Panggil ini di TransactionController@store / @update sesudah commit sukses.
     */
    public static function onTransactionChanged(int $userId, int $categoryId): array
    {
        return self::evaluateForCategory($userId, $categoryId);
    }
}
