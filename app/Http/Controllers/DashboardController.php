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
            ->active()
            ->sum(DB::raw('units * average_buy_price'));

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
            ->active()
            ->whereBetween('transaction_date', [$start, $end])
            ->with('category')
            ->get();

        $period = CarbonPeriod::create($start, $step, $end);

        $data = collect($period)->map(function (\Carbon\Carbon $dt) use ($transactions, $format) {
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

    public function indexAll(Request $request) {
        $userId = $request->user()->id;
        $range  = $request->get('range', 'month');
        [$start, $end, $step, $format] = DateRangeHelper::getPeriodSetup($range);

        // 1. Summary & Investment
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
            ->sum(DB::raw('ABS(amount)'));

        $investmentValue = (float) Investment::where('user_id', $userId)
            ->active()
            ->sum(DB::raw('units * average_buy_price'));

        // 2. Cashflow
        $transactionsForRange = Transaction::where('user_id', $userId)
            ->active()
            ->whereBetween('transaction_date', [$start, $end])
            ->with(['category:id,name,type'])
            ->select('id', 'category_id', 'amount', 'transaction_date')
            ->get();

        $period = collect(CarbonPeriod::create($start, $step, $end));
        $cashflow = $period->map(function (\Carbon\Carbon $dt) use ($transactionsForRange, $format) {
            $key = $dt->format($format);
            $filtered = $transactionsForRange->filter(function ($t) use ($format, $key) {
                return Carbon::parse($t->transaction_date)->format($format) === $key;
            });
            return [
                'period'  => $key,
                'income'  => round($filtered->where('category.type', 'income')->sum('amount'), 2),
                'expense' => round($filtered->where('category.type', 'expense')->sum(function($t){ return abs((float)$t->amount); }), 2),
            ];
        })->values();

        // 3. Allocation
        $allocation = $transactionsForRange->where('category.type', 'expense')
            ->groupBy('category.name')
            ->map(function ($items, $cat) {
                return [
                    'category' => $cat,
                    'total'    => round($items->sum(function ($t) { return abs((float)$t->amount); }), 2),
                ];
            })->values();

        // 4. Smart Insight
        $insight = SmartInsightService::checkMonthlySpendingChange($userId, 20, false);

        // 5. Recent Transactions
        $recentTransactions = Transaction::active()
            ->with(['category', 'bank'])
            ->where('user_id', $userId)
            ->orderByDesc('transaction_date')
            ->limit(5)
            ->get();

        // 6. Budgets
        $budgets = \App\Models\Budget::active()
            ->with('category')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(3)
            ->get();
        
        $budgets->transform(function ($b) {
            $calc = \App\Services\BudgetService::evaluateOne($b, 101); // 101 threshold so it doesn't notify here
            $b->spent     = $calc['spent'];
            $b->progress  = $calc['progress'];
            return $b;
        });

        return response()->json([
            'summary' => [
                'income'     => round($income, 2),
                'expense'    => round($expense, 2),
                'investment' => round($investmentValue, 2),
                'net'        => round($income - $expense, 2),
            ],
            'cashflow'     => $cashflow,
            'allocation'   => $allocation,
            'insight'      => $insight,
            'transactions' => $recentTransactions,
            'budgets'      => $budgets,
        ]);
    }
}
