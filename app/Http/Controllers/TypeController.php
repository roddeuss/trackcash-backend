<?php

namespace App\Http\Controllers;

use App\Models\Type;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TypeController extends Controller
{
    public function index()
    {
        try {
            $types = Type::all();
            return response()->json(['status' => true, 'data' => $types], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching types: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to fetch types'], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:types,name'
        ]);

        try {
            $type = Type::create(['name' => $request->name]);
            return response()->json(['status' => true, 'data' => $type], 201);
        } catch (\Exception $e) {
            Log::error('Error creating type: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to create type'], 500);
        }
    }

    public function show($id)
    {
        try {
            $type = Type::findOrFail($id);
            return response()->json(['status' => true, 'data' => $type], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Type not found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:types,name,' . $id
        ]);

        try {
            $type = Type::findOrFail($id);
            $type->update(['name' => $request->name]);
            return response()->json(['status' => true, 'data' => $type], 200);
        } catch (\Exception $e) {
            Log::error('Error updating type: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to update type'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $type = Type::findOrFail($id);
            $type->delete();
            return response()->json(['status' => true, 'message' => 'Type deleted successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting type: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to delete type'], 500);
        }
    }
}
