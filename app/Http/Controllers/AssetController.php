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
            // Ambil aset + relasi type
            $assets = Asset::with('type')
                ->active()
                ->get();

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
            'type_id'    => 'required|exists:types,id',
            'asset_code' => 'required|string|max:20|unique:assets,asset_code',
            'asset_name' => 'required|string|max:255',
            'lot_size'   => 'nullable|integer|min:1',  // ✅ default 1
        ]);

        $asset = Asset::create([
            'type_id'    => $request->type_id,
            'asset_code' => $request->asset_code,
            'asset_name' => $request->asset_name,
            'lot_size'   => $request->lot_size ?? 1,
            'created_by' => Auth::id(),
            'deleted'    => false,
        ]);

        return response()->json(['status' => true, 'data' => $asset->load('type')], 201);
    }


    /**
     * Tampilkan detail asset.
     */
    public function show($id)
    {
        try {
            $asset = Asset::with('type')
                ->active()
                ->findOrFail($id);

            return response()->json([
                'status' => true,
                'data'   => $asset,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
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
            'type_id'    => 'sometimes|exists:types,id',
            'asset_code' => 'sometimes|string|max:20|unique:assets,asset_code,' . $id,
            'asset_name' => 'sometimes|string|max:255',
            'lot_size'   => 'sometimes|integer|min:1',
        ]);

        $asset = Asset::active()->findOrFail($id);

        if ($asset->created_by !== Auth::id()) {
            return response()->json([
                'status'  => false,
                'message' => 'Unauthorized: You can only update assets you created',
            ], 403);
        }

        $asset->update([
            'type_id'    => $request->type_id    ?? $asset->type_id,
            'asset_code' => $request->asset_code ?? $asset->asset_code,
            'asset_name' => $request->asset_name ?? $asset->asset_name,
            'lot_size'   => $request->lot_size   ?? $asset->lot_size,
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['status' => true, 'data' => $asset->load('type')], 200);
    }

    /**
     * Soft delete asset (deleted = 1).
     */
    public function destroy($id)
    {
        try {
            $asset = Asset::active()->findOrFail($id);

            if ($asset->created_by !== Auth::id()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Unauthorized: You can only delete assets you created',
                ], 403);
            }

            $asset->update([
                'deleted'   => true,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Asset deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting asset: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Failed to delete asset',
            ], 500);
        }
    }
}
