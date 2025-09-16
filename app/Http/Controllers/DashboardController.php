<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Investment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // 🔹 Ringkasan (Income, Expense, Net, Investment)
    public function summary(Request $request)
    {
        $userId = $request->user()->id;
        $range  = $request->get('range', 'month');
        [$start, $end] = $this->getDateRange($range);

        // income: sum langsung (diasumsikan positif)
        $income = (float) Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', fn($q) => $q->where('type', 'income'))
            ->sum('amount');

        // expense: jumlahkan sebagai angka positif (ABS)
        $expense = (float) Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->select(DB::raw("SUM(ABS(amount)) as total"))
            ->value('total') ?? 0;

        // investmentValue: total cost basis saat ini (units * average_buy_price)
        $investmentValue = (float) Investment::where('user_id', $userId)
            ->where('deleted', false)
            ->get()
            ->sum(fn($inv) => (float)$inv->units * (float)$inv->average_buy_price);

        $net = $income - $expense;

        return response()->json([
            'income'     => round($income, 2),
            'expense'    => round($expense, 2),
            'investment' => round($investmentValue, 2),
            'net'        => round($net, 2),
        ]);
    }

    // 🔹 Grafik Cashflow
    public function cashflow(Request $request)
    {
        $userId = $request->user()->id;
        $range  = $request->get('range', 'month');
        [$start, $end, $step, $format] = $this->getPeriodSetup($range);

        $transactions = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->with('category')
            ->get();

        $period = CarbonPeriod::create($start, $step, $end);

        $data = collect($period)->map(function ($dt) use ($transactions, $format) {
            $key = $dt->format($format);

            $filtered = $transactions->filter(function ($t) use ($format, $key) {
                $tt = $t->transaction_date instanceof Carbon ? $t->transaction_date : Carbon::parse($t->transaction_date);
                return $tt->format($format) === $key;
            });

            // income: sum langsung
            $income  = (float) $filtered->where('category.type', 'income')->sum('amount');
            // expense: ABS agar positif
            $expense = (float) $filtered->where('category.type', 'expense')->sum(function ($t) {
                return abs((float)$t->amount);
            });

            return [
                'period'  => $key,
                'income'  => round($income, 2),
                'expense' => round($expense, 2),
            ];
        })->values();

        return response()->json($data);
    }

    // 🔹 Pie Chart Alokasi Pengeluaran
    public function allocation(Request $request)
    {
        $userId = $request->user()->id;
        $range  = $request->get('range', 'month');
        [$start, $end] = $this->getDateRange($range);

        $rows = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->with('category')
            ->get()
            ->groupBy(fn($t) => $t->category->name ?? 'Other')
            ->map(fn($items, $cat) => [
                'category' => $cat,
                'total'    => round($items->sum(fn($t) => abs((float)$t->amount)), 2),
            ])
            ->values();

        return response()->json($rows);
    }

    // 🔹 Helper untuk range waktu (start/end saja)
    private function getDateRange(string $range): array
    {
        $now = Carbon::now();

        return match ($range) {
            'day'   => [$now->copy()->startOfDay(),   $now->copy()->endOfDay()],
            'week'  => [$now->copy()->startOfWeek(),  $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'year'  => [$now->copy()->startOfYear(),  $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    // 🔹 Helper periodik (step & label format)
    private function getPeriodSetup(string $range): array
    {
        [$start, $end] = $this->getDateRange($range);

        return match ($range) {
            'day'   => [$start, $end, '1 day',  'Y-m-d'], // bisa jam kalau mau (1 hour)
            'week'  => [$start, $end, '1 day',  'Y-m-d'],
            'month' => [$start, $end, '1 day',  'Y-m-d'],
            'year'  => [$start, $end, '1 month', 'Y-m'],
            default => [$start, $end, '1 day',  'Y-m-d'],
        };
    }
}
