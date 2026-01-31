<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class BankController extends Controller
{
    /**
     * GET /api/banks
     * Mengembalikan daftar bank + computed_balance (saldo akhir).
     * computed_balance = balance (saldo awal) + sum(pergerakan transaksi)
     */
    public function index(Request $request)
    {
        try {
            // Ambil semua bank milik user
            $banks = Bank::where('user_id', Auth::id())
                ->active()
                ->orderBy('bank_name')
                ->get();

            if ($banks->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                ], 200);
            }

            // Agregasi pergerakan transaksi per bank_id dengan tanda yang benar
            // income  -> +amount
            // expense -> -ABS(amount)
            $movements = Transaction::query()
                ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
                ->where('transactions.user_id', Auth::id())
                ->active()
                ->whereIn('transactions.bank_id', $banks->pluck('id'))
                ->groupBy('transactions.bank_id')
                ->select(
                    'transactions.bank_id',
                    DB::raw("
                        SUM(
                            CASE
                                WHEN categories.type = 'income'  THEN transactions.amount
                                WHEN categories.type = 'expense' THEN -ABS(transactions.amount)
                                ELSE 0
                            END
                        ) AS movement
                    ")
                )
                ->pluck('movement', 'bank_id');

            // Gabungkan ke koleksi bank
            $banks->transform(function ($b) use ($movements) {
                $opening = (float) ($b->balance ?? 0);      // saldo awal (balance)
                $movement = (float) ($movements[$b->id] ?? 0); // pergerakan transaksi
                $b->computed_balance = round($opening + $movement, 2);
                return $b;
            });

            return response()->json([
                'success' => true,
                'data'    => $banks,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch banks',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/banks/{id}
     * Detail bank + computed_balance.
     */
    public function show($id)
    {
        try {
            $bank = Bank::where('id', $id)
                ->where('user_id', Auth::id())
                ->active()
                ->first();

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                ], 404);
            }

            $movement = Transaction::query()
                ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
                ->where('transactions.user_id', Auth::id())
                ->active()
                ->where('transactions.bank_id', $bank->id)
                ->select(
                    DB::raw("
                        SUM(
                            CASE
                                WHEN categories.type = 'income'  THEN transactions.amount
                                WHEN categories.type = 'expense' THEN -ABS(transactions.amount)
                                ELSE 0
                            END
                        ) AS movement
                    ")
                )
                ->value('movement');

            $opening = (float) ($bank->balance ?? 0);
            $bank->computed_balance = round($opening + (float) ($movement ?? 0), 2);

            return response()->json([
                'success' => true,
                'data'    => $bank,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bank',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'bank_name'      => 'required|string|max:255',
                'account_number' => 'required|string|max:50',
                'account_name'   => 'required|string|max:255',
                'balance'        => 'required|numeric', // saldo awal
            ]);

            $bank = Bank::create([
                'user_id'        => Auth::id(),
                'bank_name'      => $request->bank_name,
                'account_number' => $request->account_number,
                'account_name'   => $request->account_name,
                'balance'        => $request->balance, // saldo awal
                'created_by'     => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank created successfully',
                'data'    => $bank,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $bank = Bank::where('id', $id)
                ->where('user_id', Auth::id())
                ->active()
                ->first();

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                ], 404);
            }

            $request->validate([
                'bank_name'      => 'sometimes|string|max:255',
                'account_number' => 'sometimes|string|max:50',
                'account_name'   => 'sometimes|string|max:255',
                'balance'        => 'sometimes|numeric', // update saldo awal bila perlu
            ]);

            $bank->update(array_merge(
                $request->only(['bank_name', 'account_number', 'account_name', 'balance']),
                ['updated_by' => Auth::id()]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Bank updated successfully',
                'data'    => $bank,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $bank = Bank::where('id', $id)
                ->where('user_id', Auth::id())
                ->active()
                ->first();

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                ], 404);
            }

            $bank->update([
                'deleted'    => true,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank deleted successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
