<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    /**
     * List semua kategori milik user login (deleted = 0).
     */
    public function index()
    {
        try {
            $categories = Category::where('user_id', Auth::id())
                ->where('deleted', false)
                ->get();

            return response()->json([
                'status' => true,
                'data' => $categories,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching categories: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch categories',
            ], 500);
        }
    }

    /**
     * Simpan kategori baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:income,expense,investment',
            'name' => 'required|string|max:100',
        ]);

        try {
            $category = Category::create([
                'user_id' => Auth::id(),
                'type' => $request->type,
                'name' => $request->name,
                'created_by' => Auth::id(),
                'deleted' => false,
            ]);

            return response()->json([
                'status' => true,
                'data' => $category,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating category: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to create category',
            ], 500);
        }
    }

    /**
     * Tampilkan detail kategori.
     */
    public function show($id)
    {
        try {
            $category = Category::where('user_id', Auth::id())
                ->where('deleted', false)
                ->findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $category,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found',
            ], 404);
        }
    }

    /**
     * Update kategori.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'type' => 'sometimes|in:income,expense,investment',
            'name' => 'sometimes|string|max:100',
        ]);

        try {
            $category = Category::where('user_id', Auth::id())
                ->findOrFail($id);

            $category->update([
                'type' => $request->type ?? $category->type,
                'name' => $request->name ?? $category->name,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'status' => true,
                'data' => $category,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating category: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update category',
            ], 500);
        }
    }

    /**
     * Soft delete kategori.
     */
    public function destroy($id)
    {
        try {
            $category = Category::where('user_id', Auth::id())
                ->findOrFail($id);

            $category->update([
                'deleted' => true,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Category deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting category: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete category',
            ], 500);
        }
    }
}
