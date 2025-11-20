<?php

namespace App\Http\Controllers;

use App\Helpers\DateRangeHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ReportController extends Controller
{
    /**
     * Ringkasan bulanan: income, expense, net per periode.
     *
     * Output untuk:
     * - Line chart (income vs expense)
     * - Tabel ringkasan bulanan
     *
     * Request:
     *  - range: month | 3month | 6month | year | custom (sesuai DateRangeHelper)
     *  - group_by: day | month (default: month)
     */
    public function monthlySummary(Request $request)
    {
        $userId   = $request->user()->id;
        $range    = $request->get('range', 'year');
        $groupBy  = $request->get('group_by', 'month'); // day | month

        [$start, $end] = DateRangeHelper::getDateRange($range);

        // Ambil semua transaksi di range
        $transactions = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->with('category')
            ->get();

        // Tentukan step & format berdasarkan groupBy
        if ($groupBy === 'day') {
            $step   = '1 day';
            $format = 'Y-m-d';
        } else {
            $step   = '1 month';
            $format = 'Y-m';
        }

        $period = CarbonPeriod::create($start, $step, $end);

        $data = collect($period)->map(function (Carbon $dt) use ($transactions, $format, $groupBy) {
            $key = $dt->format($format);

            // Filter transaksi yang jatuh di periode ini
            $filtered = $transactions->filter(function ($t) use ($format, $key) {
                $tt = $t->transaction_date instanceof Carbon
                    ? $t->transaction_date
                    : Carbon::parse($t->transaction_date);

                return $tt->format($format) === $key;
            });

            $income = (float) $filtered
                ->where('category.type', 'income')
                ->sum('amount');

            $expense = (float) $filtered
                ->where('category.type', 'expense')
                ->sum(function ($t) {
                    return abs((float) $t->amount);
                });

            $net = $income - $expense;

            // Label yang enak dibaca (contoh: Jan, Feb, dst)
            $label = $groupBy === 'day'
                ? $dt->format('d M')
                : $dt->format('M');

            return [
                'period'       => $key,   // Y-m-d atau Y-m
                'label'        => $label, // buat di chart
                'income'       => round($income, 2),
                'expense'      => round($expense, 2),
                'net'          => round($net, 2),
            ];
        })->values();

        return response()->json($data);
    }

    /**
     * Allocation pengeluaran per kategori (pie chart).
     *
     * Mirip DashboardController::allocation, tapi ini jelas untuk "report".
     *
     * Request:
     *  - range: month | 3month | 6month | year | custom
     */
    public function categoryAllocation(Request $request)
    {
        $userId = $request->user()->id;
        $range  = $request->get('range', 'month');

        [$start, $end] = DateRangeHelper::getDateRange($range);

        $rows = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', function ($q) {
                $q->where('type', 'expense');
            })
            ->with('category')
            ->get()
            ->groupBy(function ($t) {
                return $t->category->name ?? 'Other';
            })
            ->map(function ($items, $cat) {
                $total = $items->sum(function ($t) {
                    return abs((float) $t->amount);
                });

                return [
                    'category' => $cat,
                    'total'    => round($total, 2),
                ];
            })
            ->values();

        return response()->json($rows);
    }

    /**
     * Endpoint "overview" untuk halaman laporan:
     * mengembalikan data line chart + pie chart + table sekaligus,
     * biar Next.js cukup 1x fetch.
     *
     * Request:
     *  - range: month | 3month | 6month | year | custom
     *  - group_by: day | month
     */
    public function overview(Request $request)
    {
        $userId   = $request->user()->id;
        $range    = $request->get('range', 'year');
        $groupBy  = $request->get('group_by', 'month');

        [$start, $end] = DateRangeHelper::getDateRange($range);

        // Ambil semua transaksi dalam range
        $transactions = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->with('category')
            ->get();

        // ====== Cashflow (line chart + table) ======
        if ($groupBy === 'day') {
            $step   = '1 day';
            $format = 'Y-m-d';
        } else {
            $step   = '1 month';
            $format = 'Y-m';
        }

        $period = CarbonPeriod::create($start, $step, $end);

        $cashflow = collect($period)->map(function (Carbon $dt) use ($transactions, $format, $groupBy) {
            $key = $dt->format($format);

            $filtered = $transactions->filter(function ($t) use ($format, $key) {
                $tt = $t->transaction_date instanceof Carbon
                    ? $t->transaction_date
                    : Carbon::parse($t->transaction_date);

                return $tt->format($format) === $key;
            });

            $income = (float) $filtered
                ->where('category.type', 'income')
                ->sum('amount');

            $expense = (float) $filtered
                ->where('category.type', 'expense')
                ->sum(function ($t) {
                    return abs((float) $t->amount);
                });

            $net = $income - $expense;

            $label = $groupBy === 'day'
                ? $dt->format('d M')
                : $dt->format('M');

            return [
                'period'  => $key,
                'label'   => $label,
                'income'  => round($income, 2),
                'expense' => round($expense, 2),
                'net'     => round($net, 2),
            ];
        })->values();

        // ====== Allocation (pie chart) ======
        $allocation = $transactions
            ->filter(function ($t) {
                return optional($t->category)->type === 'expense';
            })
            ->groupBy(function ($t) {
                return $t->category->name ?? 'Other';
            })
            ->map(function ($items, $cat) {
                $total = $items->sum(function ($t) {
                    return abs((float) $t->amount);
                });

                return [
                    'category' => $cat,
                    'total'    => round($total, 2),
                ];
            })
            ->values();

        // ====== Total summary (top card, optional) ======
        $totalIncome = (float) $transactions
            ->filter(fn ($t) => optional($t->category)->type === 'income')
            ->sum('amount');

        $totalExpense = (float) $transactions
            ->filter(fn ($t) => optional($t->category)->type === 'expense')
            ->sum(fn ($t) => abs((float) $t->amount));

        $summary = [
            'income'  => round($totalIncome, 2),
            'expense' => round($totalExpense, 2),
            'net'     => round($totalIncome - $totalExpense, 2),
        ];

        return response()->json([
            'summary'    => $summary,    // total income/expense/net di range
            'cashflow'   => $cashflow,   // line chart + table
            'allocation' => $allocation, // pie chart
        ]);
    }

    /**
     * NOTE (opsional):
     * Export CSV / Excel / PDF bisa dibuat di sini,
     * tapi karena di Next.js kamu sudah handle export di frontend,
     * endpoint JSON di atas biasanya sudah cukup.
     * Kalau nanti mau bikin export di backend, tinggal buat method:
     *
     *  - exportMonthlySummaryCsv(Request $request)
     *  - exportMonthlySummaryExcel(Request $request)
     *  - exportMonthlySummaryPdf(Request $request)
     *
     * dan pakai library seperti maatwebsite/excel atau dompdf.
     */
}
