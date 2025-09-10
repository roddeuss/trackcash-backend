<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;
use Exception;

class BankController extends Controller
{
    public function index()
    {
        try {
            $banks = Bank::where('user_id', auth()->id())
                ->where('deleted', false)
                ->get();

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

    public function store(Request $request)
    {
        try {
            $request->validate([
                'bank_name'      => 'required|string|max:255',
                'account_number' => 'required|string|max:50',
                'account_name'   => 'required|string|max:255',
                'balance'        => 'required|numeric',
            ]);

            $bank = Bank::create([
                'user_id'        => auth()->id(),
                'bank_name'      => $request->bank_name,
                'account_number' => $request->account_number,
                'account_name'   => $request->account_name,
                'balance'        => $request->balance,
                'created_by'     => auth()->id(),
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

    public function show($id)
    {
        try {
            $bank = Bank::where('id', $id)
                ->where('user_id', auth()->id())
                ->where('deleted', false)
                ->first();

            if (! $bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                ], 404);
            }

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

    public function update(Request $request, $id)
    {
        try {
            $bank = Bank::where('id', $id)
                ->where('user_id', auth()->id())
                ->where('deleted', false)
                ->first();

            if (! $bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                ], 404);
            }

            $request->validate([
                'bank_name'      => 'sometimes|string|max:255',
                'account_number' => 'sometimes|string|max:50',
                'account_name'   => 'sometimes|string|max:255',
                'balance'        => 'sometimes|numeric',
            ]);

            $bank->update(array_merge(
                $request->only(['bank_name', 'account_number', 'account_name', 'balance']),
                ['updated_by' => auth()->id()]
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
                ->where('user_id', auth()->id())
                ->where('deleted', false)
                ->first();

            if (! $bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found',
                ], 404);
            }

            // Soft delete via flag
            $bank->update([
                'deleted'    => true,
                'updated_by' => auth()->id(),
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
