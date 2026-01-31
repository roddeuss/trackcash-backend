<?php

namespace App\Http\Controllers;

use App\Models\RecurringTransaction;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Throwable;

class RecurringTransactionController extends Controller
{
    /**
     * List semua recurring transaction milik user.
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $items = RecurringTransaction::where('user_id', $userId)
                ->active()
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Recurring transactions fetched successfully',
                'data'    => $items,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recurring transactions',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detail satu recurring transaction.
     */
    public function show(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            $item = RecurringTransaction::where('user_id', $userId)
                ->active()
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Recurring transaction fetched successfully',
                'data'    => $item,
            ], 200);
        } catch (Throwable $e) {
            $status = ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) ? $e->getStatusCode() : 500;
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) $status = 404;

            return response()->json([
                'success' => false,
                'message' => $status === 404
                    ? 'Recurring transaction not found'
                    : 'Failed to fetch recurring transaction',
                'error'   => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Simpan recurring transaction baru (hanya rule + next_run_at).
     * Auto-generate transaksi benerannya dilakukan oleh artisan command recurring:run.
     */
    public function store(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $data = $request->validate([
                'name'        => 'required|string|max:255',
                'type'        => 'required|in:income,expense',
                'amount'      => 'required|numeric',

                'category_id' => 'nullable|exists:categories,id',
                'bank_id'     => 'nullable|exists:banks,id',
                'asset_id'    => 'nullable|exists:assets,id',

                'frequency'   => 'required|in:daily,weekly,monthly,yearly',

                'day_of_month'=> 'nullable|integer|min:1|max:31',
                'day_of_week' => 'nullable|integer|min:0|max:6',

                'start_date'  => 'nullable|date',
                'end_date'    => 'nullable|date|after_or_equal:start_date',

                'is_active'   => 'sometimes|boolean',
            ]);

            $data['user_id']   = $userId;
            $data['is_active'] = $data['is_active'] ?? true;

            // Hitung next_run_at pertama kali
            $data['next_run_at'] = $this->calculateNextRunAt($data);

            $recurring = RecurringTransaction::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Recurring transaction created successfully',
                'data'    => $recurring,
            ], 201);
        } catch (Throwable $e) {
            $status = ($e instanceof \Illuminate\Validation\ValidationException) ? 422 : 500;

            return response()->json([
                'success' => false,
                'message' => 'Failed to create recurring transaction',
                'error'   => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Update recurring transaction (rule).
     */
    public function update(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            $recurring = RecurringTransaction::where('user_id', $userId)
                ->active()
                ->findOrFail($id);

            $data = $request->validate([
                'name'        => 'sometimes|string|max:255',
                'type'        => 'sometimes|in:income,expense',
                'amount'      => 'sometimes|numeric',

                'category_id' => 'nullable|exists:categories,id',
                'bank_id'     => 'nullable|exists:banks,id',
                'asset_id'    => 'nullable|exists:assets,id',

                'frequency'   => 'sometimes|in:daily,weekly,monthly,yearly',

                'day_of_month'=> 'nullable|integer|min:1|max:31',
                'day_of_week' => 'nullable|integer|min:0|max:6',

                'start_date'  => 'nullable|date',
                'end_date'    => 'nullable|date|after_or_equal:start_date',

                'is_active'   => 'sometimes|boolean',
            ]);

            $recurring->fill($data);

            // Kalau frekuensi / tanggal berubah, atau diaktifkan lagi,
            // hitung ulang next_run_at
            if (
                array_key_exists('frequency', $data) ||
                array_key_exists('day_of_month', $data) ||
                array_key_exists('day_of_week', $data) ||
                array_key_exists('start_date', $data) ||
                array_key_exists('is_active', $data)
            ) {
                $recurring->next_run_at = $this->calculateNextRunAt($recurring->toArray());
            }

            $recurring->save();

            return response()->json([
                'success' => true,
                'message' => 'Recurring transaction updated successfully',
                'data'    => $recurring,
            ], 200);
        } catch (Throwable $e) {
            $status = ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface)
                ? $e->getStatusCode()
                : 500;
            if ($e instanceof \Illuminate\Validation\ValidationException) $status = 422;
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) $status = 404;

            return response()->json([
                'success' => false,
                'message' => $status === 404
                    ? 'Recurring transaction not found'
                    : 'Failed to update recurring transaction',
                'error'   => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Hapus recurring transaction (rule).
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;

            $recurring = RecurringTransaction::where('user_id', $userId)
                ->active()
                ->findOrFail($id);

            $recurring->update(['deleted' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Recurring transaction deleted successfully',
                'data'    => null,
            ], 200);
        } catch (Throwable $e) {
            $status = ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) 
                ? $e->getStatusCode() 
                : 500;
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) $status = 404;

            return response()->json([
                'success' => false,
                'message' => $status === 404
                    ? 'Recurring transaction not found'
                    : 'Failed to delete recurring transaction',
                'error'   => $e->getMessage(),
            ], $status);
        }
    }

    /**
     * Hitung next_run_at berdasarkan frequency, start_date, day_of_xxx.
     *
     * @param  array  $data  array data recurring (bisa dari request / model->toArray())
     * @return \Carbon\Carbon|null
     */
    protected function calculateNextRunAt(array $data): ?Carbon
    {
        // Kalau tidak aktif, ga perlu next run
        if (isset($data['is_active']) && !$data['is_active']) {
            return null;
        }

        $now = Carbon::now();

        // Base date: start_date kalau ada, kalau tidak ya today
        $start = !empty($data['start_date'])
            ? Carbon::parse($data['start_date'])->startOfDay()
            : $now->copy()->startOfDay();

        // Kalau start_date di masa depan → base = start_date
        // Kalau start_date sudah lewat → base = today
        $base = $start->greaterThan($now) ? $start : $now->copy()->startOfDay();

        $frequency = $data['frequency'] ?? 'monthly';

        switch ($frequency) {
            case 'daily':
                if ($base->greaterThan($now)) {
                    return $base;
                }
                return $now->copy()->addDay()->startOfDay();

            case 'weekly':
                $dayOfWeek = isset($data['day_of_week'])
                    ? (int) $data['day_of_week']
                    : $base->dayOfWeek;

                $next = $base->copy();
                while ($next->dayOfWeek !== $dayOfWeek) {
                    $next->addDay();
                }

                if ($next->lessThanOrEqualTo($now)) {
                    $next->addWeek();
                }

                return $next->startOfDay();

            case 'monthly':
                $dayOfMonth = isset($data['day_of_month'])
                    ? (int) $data['day_of_month']
                    : $base->day;

                $next = $base->copy()->day($dayOfMonth);

                if ($next->lessThanOrEqualTo($now)) {
                    $next->addMonthNoOverflow()->day(
                        min($dayOfMonth, $next->daysInMonth)
                    );
                }

                return $next->startOfDay();

            case 'yearly':
                $anchor = !empty($data['start_date'])
                    ? Carbon::parse($data['start_date'])->startOfDay()
                    : $now->copy()->startOfDay();

                $next = $anchor->copy();

                if ($next->lessThanOrEqualTo($now)) {
                    $next->addYear();
                }

                return $next->startOfDay();

            default:
                return null;
        }
    }
}
