<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * List semua transaksi milik user login (support filter).
     */
    public function index(Request $request)
    {
        try {
            $transactions = $this->transactionService->listTransactions($request->all(), Auth::id());

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
    public function store(StoreTransactionRequest $request)
    {
        try {
            $transaction = $this->transactionService->storeTransaction($request->validated(), Auth::id());

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
            $transaction = $this->transactionService->findTransaction((int)$id, Auth::id());

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
    public function update(UpdateTransactionRequest $request, $id)
    {
        try {
            $transaction = $this->transactionService->updateTransaction((int)$id, $request->validated(), Auth::id());

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
            $this->transactionService->deleteTransaction((int)$id, Auth::id());

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
