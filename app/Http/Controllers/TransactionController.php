<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Helpers\DateRangeHelper;
use App\Services\NotificationService; // static service
use App\Services\BudgetService;       // ⬅️ panggil BudgetService
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * List semua transaksi milik user login (support filter).
     */
    public function index(Request $request)
    {
        try {
            $query = Transaction::with(['bank', 'category', 'asset'])
                ->where('user_id', Auth::id())
                ->where('deleted', false);

            // Filter opsional
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter tanggal dd-mm-YYYY HH:mm:ss
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $start = Carbon::createFromFormat('d-m-Y H:i:s', $request->start_date)->startOfSecond();
                $end   = Carbon::createFromFormat('d-m-Y H:i:s', $request->end_date)->endOfSecond();
                $query->whereBetween('transaction_date', [$start, $end]);
            }

            // Filter range (day|week|month|year)
            if ($request->filled('range')) {
                [$start, $end] = DateRangeHelper::getDateRange($request->range);
                $query->whereBetween('transaction_date', [$start, $end]);
            }

            $transactions = $query->orderBy('transaction_date', 'desc')->get();

            return response()->json([
                'status' => true,
                'data'   => $transactions,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching transactions: '.$e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch transactions',
            ], 500);
        }
    }

    /**
     * Tambah transaksi baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'bank_id'          => 'nullable|exists:banks,id',
            'asset_id'         => 'nullable|exists:assets,id',
            'category_id'      => 'required|exists:categories,id',
            'amount'           => 'required|numeric|min:0',
            'transaction_date' => 'required|date_format:d-m-Y H:i:s',
            'description'      => 'nullable|string',
        ]);

        try {
            $transaction = Transaction::create([
                'user_id'          => Auth::id(),
                'bank_id'          => $request->bank_id,
                'asset_id'         => $request->asset_id,
                'category_id'      => $request->category_id,
                'amount'           => $request->amount,
                'transaction_date' => Carbon::createFromFormat('d-m-Y H:i:s', $request->transaction_date),
                'description'      => $request->description,
                'created_by'       => Auth::id(),
                'deleted'          => false,
            ]);

            // 🔔 Notifikasi transaksi baru (positional args → aman untuk PHP 7.4)
            NotificationService::create(
                Auth::id(),
                'transaction_created',
                'Transaksi Baru',
                'Transaksi sebesar '.number_format((float)$transaction->amount, 0, ',', '.').' berhasil dibuat.',
                ['transaction_id' => $transaction->id, 'category_id' => $transaction->category_id],
                'success',
                null
            );

            // 🔎 Evaluasi budget terkait kategori transaksi ini (buat notif threshold jika perlu)
            BudgetService::onTransactionChanged(Auth::id(), (int) $transaction->category_id);

            return response()->json([
                'status' => true,
                'data'   => $transaction->load(['bank', 'category', 'asset']),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating transaction: '.$e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Failed to create transaction',
            ], 500);
        }
    }

    /**
     * Detail transaksi.
     */
    public function show($id)
    {
        try {
            $transaction = Transaction::with(['bank', 'category', 'asset'])
                ->where('user_id', Auth::id())
                ->where('deleted', false)
                ->findOrFail($id);

            return response()->json([
                'status' => true,
                'data'   => $transaction,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Transaction not found',
            ], 404);
        }
    }

    /**
     * Update transaksi.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'bank_id'          => 'nullable|exists:banks,id',
            'asset_id'         => 'nullable|exists:assets,id',
            'category_id'      => 'sometimes|exists:categories,id',
            'amount'           => 'sometimes|numeric|min:0',
            'transaction_date' => 'sometimes|date_format:d-m-Y H:i:s',
            'description'      => 'nullable|string',
        ]);

        try {
            $transaction = Transaction::where('user_id', Auth::id())
                ->where('deleted', false)
                ->findOrFail($id);

            $oldCategoryId = (int) $transaction->category_id;

            $transaction->update([
                'bank_id'          => $request->bank_id ?? $transaction->bank_id,
                'asset_id'         => $request->asset_id ?? $transaction->asset_id,
                'category_id'      => $request->category_id ?? $transaction->category_id,
                'amount'           => $request->amount ?? $transaction->amount,
                'transaction_date' => $request->transaction_date
                    ? Carbon::createFromFormat('d-m-Y H:i:s', $request->transaction_date)
                    : $transaction->transaction_date,
                'description'      => $request->description ?? $transaction->description,
                'updated_by'       => Auth::id(),
            ]);

            // 🔔 Notifikasi update
            NotificationService::create(
                Auth::id(),
                'transaction_updated',
                'Transaksi Diperbarui',
                "Transaksi #{$transaction->id} berhasil diperbarui.",
                ['transaction_id' => $transaction->id],
                'info',
                null
            );

            // 🔎 Evaluasi budget untuk kategori terkait (kalau kategori berubah, evaluasi keduanya)
            $newCategoryId = (int) $transaction->category_id;
            BudgetService::onTransactionChanged(Auth::id(), $newCategoryId);
            if ($newCategoryId !== $oldCategoryId) {
                BudgetService::onTransactionChanged(Auth::id(), $oldCategoryId);
            }

            return response()->json([
                'status' => true,
                'data'   => $transaction->load(['bank', 'category', 'asset']),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating transaction: '.$e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Failed to update transaction',
            ], 500);
        }
    }

    /**
     * Soft delete transaksi.
     */
    public function destroy($id)
    {
        try {
            $transaction = Transaction::where('user_id', Auth::id())->findOrFail($id);
            $categoryId  = (int) $transaction->category_id;

            $transaction->update([
                'deleted'    => true,
                'updated_by' => Auth::id(),
            ]);

            // 🔔 Notifikasi delete
            NotificationService::create(
                Auth::id(),
                'transaction_deleted',
                'Transaksi Dihapus',
                "Transaksi #{$transaction->id} berhasil dihapus.",
                ['transaction_id' => $transaction->id],
                'warning',
                null
            );

            // 🔎 Evaluasi budget lagi karena pengeluaran berubah
            BudgetService::onTransactionChanged(Auth::id(), $categoryId);

            return response()->json([
                'status'  => true,
                'message' => 'Transaction deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting transaction: '.$e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Failed to delete transaction',
            ], 500);
        }
    }
}
