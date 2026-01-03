<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Part;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PartController extends Controller
{
    /**
     * Display a listing of parts
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $productId = $request->input('product_id');

        $query = Part::with(['product', 'createdBy', 'updatedBy']);

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('part_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Product filter
        if ($productId) {
            $query->where('product_id', $productId);
        }

        $parts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $parts->items(),
            'pagination' => [
                'total' => $parts->total(),
                'per_page' => $parts->perPage(),
                'current_page' => $parts->currentPage(),
                'last_page' => $parts->lastPage(),
                'from' => $parts->firstItem(),
                'to' => $parts->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created part
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'part_number' => 'nullable|string|unique:parts,part_number',
            'slug' => 'nullable|string|unique:parts,slug',
            'product_id' => 'nullable|exists:products,id',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,discontinued',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ]);
        }

        $part = Part::create([
            'name' => $request->name,
            'part_number' => $request->part_number,
            'slug' => $request->slug,
            'product_id' => $request->product_id,
            'description' => $request->description,
            'status' => $request->status ?? 'active',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Part created successfully',
            'data' => $part->load(['product', 'createdBy', 'updatedBy']),
        ]);
    }

    /**
     * Display the specified part
     */
    public function show(string $id): JsonResponse
    {
        $part = Part::with(['product', 'createdBy', 'updatedBy'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $part,
        ]);
    }

    /**
     * Update the specified part
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $part = Part::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'part_number' => 'nullable|string|unique:parts,part_number,' . $id,
            'slug' => 'nullable|string|unique:parts,slug,' . $id,
            'product_id' => 'nullable|exists:products,id',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,discontinued',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ]);
        }

        $part->update([
            'name' => $request->name,
            'part_number' => $request->part_number ?? $part->part_number,
            'slug' => $request->slug ?? $part->slug,
            'product_id' => $request->product_id,
            'description' => $request->description,
            'status' => $request->status ?? $part->status,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Part updated successfully',
            'data' => $part->load(['product', 'createdBy', 'updatedBy']),
        ]);
    }

    /**
     * Remove the specified part
     */
    public function destroy(string $id): JsonResponse
    {
        $part = Part::findOrFail($id);
        $part->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Part deleted successfully',
        ]);
    }
}
