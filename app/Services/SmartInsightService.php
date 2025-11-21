<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SmartInsightService
{
    /**
     * Insight global: perubahan total expense month vs previous month.
     * (ini contoh, mungkin kamu sudah punya versi mirip).
     */
    public static function checkMonthlySpendingChange(
        int $userId,
        int $thresholdPercent = 20,
        bool $fireNotification = false
    ): array {
        $now         = Carbon::now();
        $thisMonth   = $now->copy()->startOfMonth();
        $lastMonth   = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endThis     = $now->copy()->endOfMonth();
        $endLast     = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $current = (float) self::sumExpense($userId, $thisMonth, $endThis);
        $previous = (float) self::sumExpense($userId, $lastMonth, $endLast);

        if ($previous <= 0 && $current <= 0) {
            return [
                'has_change'      => false,
                'current'         => $current,
                'previous'        => $previous,
                'percent_change'  => 0,
                'direction'       => 'flat',
                'message'         => null,
            ];
        }

        if ($previous <= 0) {
            $percent = 100;
        } else {
            $percent = (($current - $previous) / $previous) * 100;
        }

        $direction = 'flat';
        if ($percent > 0) $direction = 'up';
        if ($percent < 0) $direction = 'down';

        $hasChange = abs($percent) >= $thresholdPercent;

        $message = null;
        if ($hasChange) {
            if ($direction === 'up') {
                $message = "Pengeluaran bulan ini naik sekitar ".round($percent)."% dibanding bulan lalu.";
            } elseif ($direction === 'down') {
                $message = "Good job! Pengeluaran bulan ini turun sekitar ".abs(round($percent))."% dibanding bulan lalu.";
            }
        }

        // kalau mau, di sini bisa panggil NotificationService::create(...) saat $fireNotification === true

        return [
            'has_change'      => $hasChange,
            'current'         => round($current, 2),
            'previous'        => round($previous, 2),
            'percent_change'  => round($percent, 1),
            'direction'       => $direction,
            'message'         => $message,
        ];
    }

    protected static function sumExpense(int $userId, Carbon $start, Carbon $end): float
    {
        return (float) Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', function ($q) {
                $q->where('type', 'expense');
            })
            ->select(DB::raw('SUM(ABS(amount)) as total'))
            ->value('total') ?? 0;
    }

    /**
     * 🔹 Smart Suggestions per kategori.
     * Balikkan array suggestion yang bisa kamu render di frontend.
     */
    public static function getCategorySuggestions(
        int $userId,
        int $thresholdPercent = 30,
        int $minAmount = 500000
    ): array {
        $now       = Carbon::now();
        $thisMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endThis   = $now->copy()->endOfMonth();
        $endLast   = $now->copy()->subMonthNoOverflow()->endOfMonth();

        // total per kategori bulan ini
        $currentRows = self::sumExpenseByCategory($userId, $thisMonth, $endThis);
        // total per kategori bulan lalu
        $prevRows    = self::sumExpenseByCategory($userId, $lastMonth, $endLast);

        $currentTotal = array_sum(array_column($currentRows, 'total'));

        $suggestions = [];

        foreach ($currentRows as $row) {
            $catId   = $row['category_id'];
            $catName = $row['category_name'];
            $current = $row['total'];
            $prev    = isset($prevRows[$catId]) ? $prevRows[$catId]['total'] : 0;

            if ($current < $minAmount) {
                continue; // nominal kecil, skip
            }

            // percent change
            if ($prev <= 0 && $current <= 0) {
                $percentChange = 0;
            } elseif ($prev <= 0) {
                $percentChange = 100;
            } else {
                $percentChange = (($current - $prev) / $prev) * 100;
            }

            $share = $currentTotal > 0 ? ($current / $currentTotal) * 100 : 0;

            // rules: Naik signifikan ATAU porsi terlalu besar
            if (abs($percentChange) < $thresholdPercent && $share < 25) {
                continue;
            }

            $suggestions[] = [
                'category_id'     => $catId,
                'category_name'   => $catName,
                'current'         => round($current, 2),
                'previous'        => round($prev, 2),
                'percent_change'  => round($percentChange, 1),
                'share'           => round($share, 1),
                'advice'          => self::buildAdviceText($catName, $percentChange, $share, $current),
            ];
        }

        return $suggestions;
    }

    /**
     * Bangun kalimat saran dari kategori + angka.
     */
    protected static function buildAdviceText(
        string $category,
        float $percentChange,
        float $share,
        float $current
    ): string {
        $percent = round($percentChange);
        $shareRound = round($share);
        $nominal = number_format($current, 0, ',', '.');

        $lower = mb_strtolower($category, 'UTF-8');

        // contoh rule khusus
        if (str_contains($lower, 'hiburan') || str_contains($lower, 'entertain')) {
            return "Pengeluaran di kategori Hiburan (".$category.") bulan ini sekitar Rp {$nominal} ({$shareRound}% dari total) dan berubah {$percent}% dibanding bulan lalu. Coba atur batas bulanan untuk hiburan dan prioritaskan aktivitas gratis atau lebih murah.";
        }

        if (str_contains($lower, 'makan') || str_contains($lower, 'food') || str_contains($lower, 'delivery')) {
            return "Pengeluaran untuk ".$category." cukup besar (Rp {$nominal}, {$shareRound}% dari total). Pertimbangkan untuk lebih sering masak di rumah atau atur jadwal makan di luar agar lebih terkontrol.";
        }

        if (str_contains($lower, 'langganan') || str_contains($lower, 'subscription')) {
            return "Kategori ".$category." tampaknya menyedot cukup banyak biaya (Rp {$nominal}). Coba review kembali langganan yang jarang dipakai dan hentikan yang tidak perlu.";
        }

        // generic advice
        if ($percentChange > 0) {
            return "Pengeluaran di kategori ".$category." naik sekitar {$percent}% (Rp {$nominal}) dan mengambil {$shareRound}% dari total. Jika tidak urgent, pertimbangkan untuk mengurangi pengeluaran di kategori ini bulan depan.";
        }

        if ($percentChange < 0) {
            return "Good job! Pengeluaran kategori ".$category." turun sekitar ".abs($percent)."% (Rp {$nominal}). Pertahankan kebiasaan baik ini agar cashflow tetap sehat.";
        }

        return "Pengeluaran kategori ".$category." bulan ini sekitar Rp {$nominal} ({$shareRound}% dari total). Pertimbangkan apakah nominal ini sudah sesuai prioritas dan kebutuhanmu.";
    }

    /**
     * Helper: total per kategori dalam range tanggal.
     *
     * @return array<int, array{category_id:int, category_name:string, total:float}>
     */
    protected static function sumExpenseByCategory(int $userId, Carbon $start, Carbon $end): array
    {
        $rows = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', function ($q) {
                $q->where('type', 'expense');
            })
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->groupBy('transactions.category_id', 'categories.name')
            ->select(
                'transactions.category_id',
                'categories.name as category_name',
                DB::raw('SUM(ABS(transactions.amount)) as total')
            )
            ->get();

        // indekskan by category_id biar gampang
        $result = [];
        foreach ($rows as $row) {
            $result[$row->category_id] = [
                'category_id'   => (int) $row->category_id,
                'category_name' => (string) $row->category_name,
                'total'         => (float) $row->total,
            ];
        }

        return $result;
    }
}
