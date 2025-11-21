<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Investment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use App\Helpers\DateRangeHelper;
use App\Services\SmartInsightService;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $userId = $request->user()->id;
        $range  = $request->get('range', 'month');
        [$start, $end] = DateRangeHelper::getDateRange($range);

        $income = (float) Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', function ($q) {
                return $q->where('type', 'income');
            })
            ->sum('amount');

        $expense = (float) Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', function ($q) {
                return $q->where('type', 'expense');
            })
            ->select(DB::raw("SUM(ABS(amount)) as total"))
            ->value('total') ?? 0;

        $investmentValue = (float) Investment::where('user_id', $userId)
            ->where('deleted', false)
            ->get()
            ->sum(function ($inv) {
                return (float) $inv->units * (float) $inv->average_buy_price;
            });

        $net = $income - $expense;

        return response()->json([
            'income'     => round($income, 2),
            'expense'    => round($expense, 2),
            'investment' => round($investmentValue, 2),
            'net'        => round($net, 2),
        ]);
    }

    public function cashflow(Request $request)
    {
        $userId = $request->user()->id;
        $range  = $request->get('range', 'month');
        [$start, $end, $step, $format] = DateRangeHelper::getPeriodSetup($range);

        $transactions = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->with('category')
            ->get();

        $period = CarbonPeriod::create($start, $step, $end);

        $data = collect($period)->map(function ($dt) use ($transactions, $format) {
            $key = $dt->format($format);

            $filtered = $transactions->filter(function ($t) use ($format, $key) {
                $tt = $t->transaction_date instanceof Carbon
                    ? $t->transaction_date
                    : Carbon::parse($t->transaction_date);
                return $tt->format($format) === $key;
            });

            $income = (float) $filtered->where('category.type', 'income')->sum('amount');
            $expense = (float) $filtered->where('category.type', 'expense')->sum(function ($t) {
                return abs((float) $t->amount);
            });

            return [
                'period'  => $key,
                'income'  => round($income, 2),
                'expense' => round($expense, 2),
            ];
        })->values();

        return response()->json($data);
    }

    public function allocation(Request $request)
    {
        $userId = $request->user()->id;
        $range  = $request->get('range', 'month');
        [$start, $end] = DateRangeHelper::getDateRange($range);

        $rows = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereHas('category', function ($q) {
                return $q->where('type', 'expense');
            })
            ->with('category')
            ->get()
            ->groupBy(function ($t) {
                return $t->category->name ?? 'Other';
            })
            ->map(function ($items, $cat) {
                return [
                    'category' => $cat,
                    'total'    => round($items->sum(function ($t) {
                        return abs((float) $t->amount);
                    }), 2),
                ];
            })
            ->values();

        return response()->json($rows);
    }

    public function smartInsight(Request $request)
    {
        $userId    = $request->user()->id;
        $threshold = (int) $request->get('threshold', 20);

        // Di endpoint dashboard, biasanya kita TIDAK mau spam notif tiap user buka halaman.
        // Jadi fireNotification = false → hanya hitung & return data.
        $result = SmartInsightService::checkMonthlySpendingChange(
            $userId,
            $threshold,
            false // ⬅️ tidak kirim notif di sini
        );

        return response()->json($result);
    }

    public function smartSuggestions(Request $request)
    {
        $userId          = $request->user()->id;
        $threshold       = (int) $request->get('threshold', 30);
        $minAmount       = (int) $request->get('min_amount', 500000);

        $suggestions = SmartInsightService::getCategorySuggestions(
            $userId,
            $threshold,
            $minAmount
        );

        return response()->json([
            'success'     => true,
            'suggestions' => $suggestions,
        ]);
    }
}
