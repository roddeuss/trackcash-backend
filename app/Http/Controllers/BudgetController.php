<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Helpers\BudgetHelper;


class BudgetController extends Controller
{
    /**
     * GET /api/budgets
     * List budgets milik user + computed fields (spent, remaining, progress).
     */
    public function index(Request $request)
    {
        try {
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

            $budgets->transform(function (Budget $b) {
                [$start, $end] = $this->getBudgetWindow($b);

                // Total pengeluaran untuk kategori ini pada window budget
                $spent = (float) Transaction::query()
                    ->where('transactions.user_id', Auth::id())
                    ->where('transactions.deleted', false)
                    ->where('transactions.category_id', $b->category_id)
                    ->whereBetween('transactions.transaction_date', [$start, $end])
                    ->select(DB::raw('SUM(ABS(transactions.amount)) as total'))
                    ->value('total');

                $spent      = round($spent ?: 0, 2);
                $remaining  = round(max(0, (float)$b->amount - $spent), 2);
                $progress   = (float)$b->amount > 0
                    ? round(min(100, ($spent / (float)$b->amount) * 100), 2)
                    : 0;

                // tambahkan properti terhitung
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
            // Untuk custom, wajib ada start/end
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
     * Detail budget + computed fields.
     */
    public function show($id)
    {
        try {
            $budget = Budget::with('category')
                ->where('user_id', Auth::id())
                ->where('deleted', false)
                ->findOrFail($id);

            [$start, $end] = $this->getBudgetWindow($budget);

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
                // non-custom: clear custom dates
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
