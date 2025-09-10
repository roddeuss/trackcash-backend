<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InvestmentController extends Controller
{
    /**
     * List semua investasi milik user login.
     */
    public function index()
    {
        try {
            $investments = Investment::with('asset')
                ->where('user_id', Auth::id())
                ->where('deleted', false)
                ->orderBy('buy_date', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $investments,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching investments: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch investments',
            ], 500);
        }
    }

    /**
     * Tambah investasi baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'units' => 'required|numeric|min:0',
            'buy_price_per_unit' => 'required|numeric|min:0',
            'buy_date' => 'required|date',
            'current_price_per_unit' => 'nullable|numeric|min:0',
        ]);

        try {
            $investment = Investment::create([
                'user_id' => Auth::id(),
                'asset_id' => $request->asset_id,
                'units' => $request->units,
                'buy_price_per_unit' => $request->buy_price_per_unit,
                'buy_date' => $request->buy_date,
                'current_price_per_unit' => $request->current_price_per_unit,
                'created_by' => Auth::id(),
                'deleted' => false,
            ]);

            return response()->json([
                'status' => true,
                'data' => $investment->load('asset'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating investment: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to create investment',
            ], 500);
        }
    }

    /**
     * Detail investasi.
     */
    public function show($id)
    {
        try {
            $investment = Investment::with('asset')
                ->where('user_id', Auth::id())
                ->where('deleted', false)
                ->findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $investment,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Investment not found',
            ], 404);
        }
    }

    /**
     * Update investasi.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'asset_id' => 'sometimes|exists:assets,id',
            'units' => 'sometimes|numeric|min:0',
            'buy_price_per_unit' => 'sometimes|numeric|min:0',
            'buy_date' => 'sometimes|date',
            'current_price_per_unit' => 'nullable|numeric|min:0',
        ]);

        try {
            $investment = Investment::where('user_id', Auth::id())
                ->where('deleted', false)
                ->findOrFail($id);

            $investment->update([
                'asset_id' => $request->asset_id ?? $investment->asset_id,
                'units' => $request->units ?? $investment->units,
                'buy_price_per_unit' => $request->buy_price_per_unit ?? $investment->buy_price_per_unit,
                'buy_date' => $request->buy_date ?? $investment->buy_date,
                'current_price_per_unit' => $request->current_price_per_unit ?? $investment->current_price_per_unit,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'status' => true,
                'data' => $investment->load('asset'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating investment: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update investment',
            ], 500);
        }
    }

    /**
     * Soft delete investasi.
     */
    public function destroy($id)
    {
        try {
            $investment = Investment::where('user_id', Auth::id())
                ->findOrFail($id);

            $investment->update([
                'deleted' => true,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Investment deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting investment: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete investment',
            ], 500);
        }
    }
}
