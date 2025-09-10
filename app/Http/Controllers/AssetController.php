<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AssetController extends Controller
{
    /**
     * Tampilkan semua asset (kecuali yang deleted = 1).
     */
    public function index()
    {
        try {
            $assets = Asset::where('deleted', false)->get();

            return response()->json([
                'status' => true,
                'data' => $assets,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching assets: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch assets',
            ], 500);
        }
    }

    /**
     * Simpan asset baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string|max:50',
            'code' => 'required|string|max:20|unique:assets,code',
            'name' => 'required|string|max:255',
        ]);

        try {
            $asset = Asset::create([
                'type' => $request->type,
                'code' => $request->code,
                'name' => $request->name,
                'created_by' => Auth::id(),
                'deleted' => false,
            ]);

            return response()->json([
                'status' => true,
                'data' => $asset,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating asset: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to create asset',
            ], 500);
        }
    }

    /**
     * Tampilkan detail asset.
     */
    public function show($id)
    {
        try {
            $asset = Asset::where('deleted', false)->findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $asset,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Asset not found',
            ], 404);
        }
    }

    /**
     * Update data asset.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'type' => 'sometimes|string|max:50',
            'code' => 'sometimes|string|max:20|unique:assets,code,' . $id,
            'name' => 'sometimes|string|max:255',
        ]);

        try {
            $asset = Asset::findOrFail($id);

            $asset->update([
                'type' => $request->type ?? $asset->type,
                'code' => $request->code ?? $asset->code,
                'name' => $request->name ?? $asset->name,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'status' => true,
                'data' => $asset,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating asset: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update asset',
            ], 500);
        }
    }

    /**
     * Soft delete asset (deleted = 1).
     */
    public function destroy($id)
    {
        try {
            $asset = Asset::findOrFail($id);
            $asset->update([
                'deleted' => true,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Asset deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting asset: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete asset',
            ], 500);
        }
    }
}
