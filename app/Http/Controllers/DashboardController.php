<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Investment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DashboardController extends Controller
{
    // 🔹 Ringkasan (Income, Expense, Net, Investment, Saldo)
    public function summary(Request $request)
    {
        $userId = $request->user()->id;
        $range = $request->get('range', 'month'); // default bulan ini
        [$start, $end] = $this->getDateRange($range);

        $income = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', fn($q) => $q->where('type', 'income'))
            ->sum('amount');

        $expense = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->sum('amount');

        $investmentValue = Investment::where('user_id', $userId)
            ->whereBetween('buy_date', [$start, $end])
            ->get()
            ->sum(fn($inv) => $inv->units * $inv->buy_price_per_unit);

        $net = $income - $expense;

        return response()->json([
            'income' => $income,
            'expense' => $expense,
            'investment' => $investmentValue,
            'net' => $net,
        ]);
    }

    // 🔹 Grafik Cashflow
    public function cashflow(Request $request)
    {
        $userId = $request->user()->id;
        $range = $request->get('range', 'month');
        [$start, $end] = $this->getDateRange($range);

        $period = CarbonPeriod::create($start, $end);
        $transactions = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->with('category')
            ->get();

        $data = collect($period)->map(function ($date) use ($transactions, $range) {
            $format = $range === 'year' ? 'Y-m' : 'Y-m-d';
            $dayKey = $date->format($format);

            $filtered = $transactions->filter(
                fn($t) =>
                $t->transaction_date->format($format) === $dayKey
            );

            return [
                'period' => $dayKey,
                'income' => $filtered->where('category.type', 'income')->sum('amount'),
                'expense' => $filtered->where('category.type', 'expense')->sum('amount'),
            ];
        });

        return response()->json($data->values());
    }

    // 🔹 Pie Chart Alokasi Pengeluaran
    public function allocation(Request $request)
    {
        $userId = $request->user()->id;
        $range = $request->get('range', 'month');
        [$start, $end] = $this->getDateRange($range);

        $data = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', fn($q) => $q->where('type', 'expense'))
            ->with('category')
            ->get()
            ->groupBy(fn($t) => $t->category->name ?? 'Other')
            ->map(fn($items, $cat) => [
                'category' => $cat,
                'total' => $items->sum('amount'),
            ])
            ->values();

        return response()->json($data);
    }

    // 🔹 Helper untuk range waktu
    private function getDateRange(string $range): array
    {
        $now = Carbon::now();

        return match ($range) {
            'day'   => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }
}
