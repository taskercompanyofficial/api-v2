<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\QueryFilterTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PartController extends Controller
{
    use QueryFilterTrait;
    /**
     * Display a listing of parts
     */
    public function index(Request $request): JsonResponse
    {
        $page = $request->page ?? 1;
        $perPage = $request->perPage ?? 50;

        $query = Part::with(['product', 'createdBy', 'updatedBy']);

         if ($request->has('name') && $request->name) {
            $searchTerm = $request->name;
            
            $query->where(function ($q) use ($searchTerm) {
                // Search in work order fields
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('part_number', 'like', "%{$searchTerm}%")
                  ->orWhere('slug', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        $this->applyJsonFilters($query, $request);

        // Apply sorting
        $this->applySorting($query, $request);

        // Fallback to latest if no sorting specified
        if (!$request->has('sort')) {
            $query->latest();
        }

        $parts = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
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
        $request->validate([
            'name' => 'required|string|max:255',
            'product_id' => 'nullable|exists:products,id',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,discontinued',
        ]);
        $request->slug = Str::slug($request->name);
        $request->part_number = 'PART-' . strtoupper(Str::random(8));

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
    public function partsRaw(Request $request)
    {
        $searchQuery = $request->input('name');
        $productId = $request->input('product_id');

        $query = Part::query()->where('status', 'active');
           if ($productId) {
            $query->where('product_id', $productId);
        }
        if ($searchQuery) {
            $query->where('name', 'LIKE', "%{$searchQuery}%");
        }
     
            
        try {
            $parts = $query->select('id', 'name', 'description')
                ->limit(50)
                ->get()
                ->map(function ($part) {
                    return [
                        'value' => $part->id,
                        'label' => $part->name,
                        'description' => $part->description,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $parts,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve parts.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
