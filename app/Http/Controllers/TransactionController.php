<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    /**
     * List semua transaksi milik user login.
     */
    public function index(Request $request)
    {
        try {
            $query = Transaction::with(['bank', 'category', 'asset'])
                ->where('user_id', Auth::id())
                ->where('deleted', false);

            // Filter opsional
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
            }

            $transactions = $query->orderBy('transaction_date', 'desc')->get();

            return response()->json([
                'status' => true,
                'data' => $transactions,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching transactions: ' . $e->getMessage());

            return response()->json([
                'status' => false,
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
            'bank_id' => 'nullable|exists:banks,id',
            'asset_id' => 'nullable|exists:assets,id',
            'category_id' => 'required|exists:categories,id',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'bank_id' => $request->bank_id,
                'asset_id' => $request->asset_id,
                'category_id' => $request->category_id,
                'amount' => $request->amount,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'created_by' => Auth::id(),
                'deleted' => false,
            ]);

            return response()->json([
                'status' => true,
                'data' => $transaction->load(['bank', 'category', 'asset']),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating transaction: ' . $e->getMessage());

            return response()->json([
                'status' => false,
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
                'data' => $transaction,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
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
            'bank_id' => 'nullable|exists:banks,id',
            'asset_id' => 'nullable|exists:assets,id',
            'category_id' => 'sometimes|exists:categories,id',
            'amount' => 'sometimes|numeric|min:0',
            'transaction_date' => 'sometimes|date',
            'description' => 'nullable|string',
        ]);

        try {
            $transaction = Transaction::where('user_id', Auth::id())
                ->where('deleted', false)
                ->findOrFail($id);

            $transaction->update([
                'bank_id' => $request->bank_id ?? $transaction->bank_id,
                'asset_id' => $request->asset_id ?? $transaction->asset_id,
                'category_id' => $request->category_id ?? $transaction->category_id,
                'amount' => $request->amount ?? $transaction->amount,
                'transaction_date' => $request->transaction_date ?? $transaction->transaction_date,
                'description' => $request->description ?? $transaction->description,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'status' => true,
                'data' => $transaction->load(['bank', 'category', 'asset']),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating transaction: ' . $e->getMessage());

            return response()->json([
                'status' => false,
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
            $transaction = Transaction::where('user_id', Auth::id())
                ->findOrFail($id);

            $transaction->update([
                'deleted' => true,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Transaction deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting transaction: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete transaction',
            ], 500);
        }
    }
}
