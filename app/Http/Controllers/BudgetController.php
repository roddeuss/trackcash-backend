<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\Notification;            // ⬅️ tambahkan
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Helpers\BudgetHelper;
use App\Services\NotificationService;   // ⬅️ tambahkan

class BudgetController extends Controller
{
    /**
     * GET /api/budgets
     * List budgets milik user + computed fields (spent, remaining, progress).
     * Support ?threshold=80 atau 0.8 untuk trigger notifikasi.
     */
    public function index(Request $request)
    {
        try {
            // Normalisasi threshold: bisa 80 (persen) atau 0.8
            $thr = $request->has('threshold') ? (float)$request->get('threshold') : 0.8;
            $threshold = $thr > 1 ? $thr / 100 : $thr;
            if ($threshold <= 0) $threshold = 0.8;
            if ($threshold > 1)  $threshold = 1;

            $budgets = Budget::with('category')
                ->where('user_id', Auth::id())
                ->where('deleted', false)
                ->orderByDesc('id')
                ->get();

            if ($budgets->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                ], 200);
            }

            $userId = Auth::id();

            $budgets->transform(function (Budget $b) use ($threshold, $userId) {
                [$start, $end] = BudgetHelper::getBudgetWindow($b);

                // Total pengeluaran untuk kategori ini pada window budget
                $spent = (float) Transaction::query()
                    ->where('transactions.user_id', $userId)
                    ->where('transactions.deleted', false)
                    ->where('transactions.category_id', $b->category_id)
                    ->whereBetween('transactions.transaction_date', [$start, $end])
                    ->select(DB::raw('SUM(ABS(transactions.amount)) as total'))
                    ->value('total');

                $spent      = round($spent ?: 0, 2);
                $remaining  = round(max(0, (float)$b->amount - $spent), 2);
                $progress   = (float)$b->amount > 0
                    ? round(min(100, ($spent / (float)$b->amount) * 100), 2)
                    : 0.0;

                // 🔔 Notifikasi "hampir over budget" (sekali per window)
                try {
                    if ((float)$b->amount > 0 && $spent >= ((float)$b->amount * $threshold)) {
                        $windowStartKey = $start->toDateString();
                        $windowEndKey   = $end->toDateString();

                        $already = Notification::query()
                            ->where('user_id', $userId)
                            ->where('type', 'budget_threshold')
                            ->where('is_read', false)
                            ->where('data->budget_id', $b->id)
                            ->where('data->window_start', $windowStartKey)
                            ->where('data->window_end', $windowEndKey)
                            ->exists();

                        if (!$already) {
                            $pct = $progress;
                            $cat = optional($b->category)->name ?: '-';
                            $msg = 'Budget kategori "'.$cat.'" sudah '.$pct.'% terpakai ('
                                 . number_format($spent, 2, ',', '.') . ' dari '
                                 . number_format((float)$b->amount, 2, ',', '.') . ').';

                            NotificationService::create(
                                $userId,
                                'budget_threshold',
                                'Budget Hampir Habis',
                                $msg,
                                [
                                    'budget_id'    => $b->id,
                                    'category_id'  => $b->category_id,
                                    'window_start' => $windowStartKey,
                                    'window_end'   => $windowEndKey,
                                    'progress'     => $pct,
                                    'spent'        => $spent,
                                    'amount'       => (float)$b->amount,
                                ],
                                'warning',
                                null
                            );
                        }
                    }
                } catch (\Throwable $nex) {
                    Log::warning('Budget threshold notification failed: '.$nex->getMessage());
                }

                // Tambahkan properti terhitung ke model (agar ikut terkirim)
                $b->spent     = $spent;
                $b->remaining = $remaining;
                $b->progress  = $progress;
                $b->window    = [
                    'start' => $start->toDateTimeString(),
                    'end'   => $end->toDateTimeString(),
                ];

                return $b;
            });

            return response()->json([
                'success' => true,
                'data'    => $budgets,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching budgets', ['err' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch budgets',
            ], 500);
        }
    }

    /**
     * POST /api/budgets
     * Create budget baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name'        => ['nullable', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:0'],
            'period'      => ['required', Rule::in(['monthly', 'weekly', 'yearly', 'custom'])],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        try {
            if ($request->period === 'custom') {
                if (!$request->start_date || !$request->end_date) {
                    return response()->json([
                        'success' => false,
                        'message' => 'For custom period, start_date and end_date are required.',
                    ], 422);
                }
            }

            $budget = Budget::create([
                'user_id'     => Auth::id(),
                'category_id' => $request->category_id,
                'name'        => $request->name,
                'amount'      => $request->amount,
                'period'      => $request->period,
                'start_date'  => $request->period === 'custom' ? $request->start_date : null,
                'end_date'    => $request->period === 'custom' ? $request->end_date : null,
                'created_by'  => Auth::id(),
                'deleted'     => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Budget created successfully',
                'data'    => $budget->load('category'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating budget', ['err' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create budget',
            ], 500);
        }
    }

    /**
     * GET /api/budgets/{id}
     * Detail budget + computed fields (+ notifikasi jika mendekati budget).
     * Support ?threshold=80 atau 0.8.
     */
    public function show($id, Request $request)
    {
        try {
            $thr = $request->has('threshold') ? (float)$request->get('threshold') : 0.8;
            $threshold = $thr > 1 ? $thr / 100 : $thr;
            if ($threshold <= 0) $threshold = 0.8;
            if ($threshold > 1)  $threshold = 1;

            $budget = Budget::with('category')
                ->where('user_id', Auth::id())
                ->where('deleted', false)
                ->findOrFail($id);

            [$start, $end] = BudgetHelper::getBudgetWindow($budget);

            $spent = (float) Transaction::query()
                ->where('transactions.user_id', Auth::id())
                ->where('transactions.deleted', false)
                ->where('transactions.category_id', $budget->category_id)
                ->whereBetween('transactions.transaction_date', [$start, $end])
                ->select(DB::raw('SUM(ABS(transactions.amount)) as total'))
                ->value('total');

            $spent     = round($spent ?: 0, 2);
            $remaining = round(max(0, (float)$budget->amount - $spent), 2);
            $progress  = (float)$budget->amount > 0
                ? round(min(100, ($spent / (float)$budget->amount) * 100), 2)
                : 0;

            // 🔔 Notifikasi untuk budget ini (sekali per window)
            try {
                if ((float)$budget->amount > 0 && $spent >= ((float)$budget->amount * $threshold)) {
                    $windowStartKey = $start->toDateString();
                    $windowEndKey   = $end->toDateString();

                    $already = Notification::query()
                        ->where('user_id', Auth::id())
                        ->where('type', 'budget_threshold')
                        ->where('is_read', false)
                        ->where('data->budget_id', $budget->id)
                        ->where('data->window_start', $windowStartKey)
                        ->where('data->window_end', $windowEndKey)
                        ->exists();

                    if (!$already) {
                        $pct = $progress;
                        $cat = optional($budget->category)->name ?: '-';
                        $msg = 'Budget kategori "'.$cat.'" sudah '.$pct.'% terpakai ('
                             . number_format($spent, 2, ',', '.') . ' dari '
                             . number_format((float)$budget->amount, 2, ',', '.') . ').';

                        NotificationService::create(
                            Auth::id(),
                            'budget_threshold',
                            'Budget Hampir Habis',
                            $msg,
                            [
                                'budget_id'    => $budget->id,
                                'category_id'  => $budget->category_id,
                                'window_start' => $windowStartKey,
                                'window_end'   => $windowEndKey,
                                'progress'     => $pct,
                                'spent'        => $spent,
                                'amount'       => (float)$budget->amount,
                            ],
                            'warning',
                            null
                        );
                    }
                }
            } catch (\Throwable $nex) {
                Log::warning('Budget threshold notification (show) failed: '.$nex->getMessage());
            }

            $payload = $budget->toArray();
            $payload['spent']     = $spent;
            $payload['remaining'] = $remaining;
            $payload['progress']  = $progress;
            $payload['window']    = [
                'start' => $start->toDateTimeString(),
                'end'   => $end->toDateTimeString(),
            ];

            return response()->json([
                'success' => true,
                'data'    => $payload,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching budget detail', ['err' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Budget not found',
            ], 404);
        }
    }

    /**
     * PUT /api/budgets/{id}
     * Update budget.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name'        => ['nullable', 'string', 'max:255'],
            'amount'      => ['sometimes', 'numeric', 'min:0'],
            'period'      => ['sometimes', Rule::in(['monthly', 'weekly', 'yearly', 'custom'])],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        try {
            $budget = Budget::where('user_id', Auth::id())
                ->where('deleted', false)
                ->findOrFail($id);

            $period     = $request->has('period') ? $request->period : $budget->period;
            $start_date = $request->start_date ?? $budget->start_date;
            $end_date   = $request->end_date ?? $budget->end_date;

            if ($period === 'custom') {
                if (!$start_date || !$end_date) {
                    return response()->json([
                        'success' => false,
                        'message' => 'For custom period, start_date and end_date are required.',
                    ], 422);
                }
            } else {
                $start_date = null;
                $end_date   = null;
            }

            $budget->update([
                'category_id' => $request->category_id ?? $budget->category_id,
                'name'        => $request->has('name') ? $request->name : $budget->name,
                'amount'      => $request->amount ?? $budget->amount,
                'period'      => $period,
                'start_date'  => $start_date,
                'end_date'    => $end_date,
                'updated_by'  => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Budget updated successfully',
                'data'    => $budget->load('category'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating budget', ['err' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update budget',
            ], 500);
        }
    }

    /**
     * DELETE /api/budgets/{id}
     * Soft delete budget.
     */
    public function destroy($id)
    {
        try {
            $budget = Budget::where('user_id', Auth::id())->findOrFail($id);

            $budget->update([
                'deleted'    => true,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Budget deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting budget', ['err' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete budget',
            ], 500);
        }
    }
}
