<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\StoreItems;
use App\QueryFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreItemController extends Controller
{
    use QueryFilterTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = StoreItems::query()->with([
            'createdBy:id,name',
            'updatedBy:id,name',
            'itemInstances'
        ]);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'name',
            'sku',
            'price',
            'status',
            'created_at',
            'updated_at'
        ]);

        // Stock filters (your custom logic)

        $query->withCount([
            'itemInstances as available_count' => function ($query) {
                $query->where('status', 'active');
            },
            'itemInstances as reserved_count' => function ($query) {
                $query->where('status', 'reserved');
            }
        ]);
        // Paginated result

        // Summary
        $allItems = $query->get();

        $inventorySummary = [
            'total_items' => $allItems->count(),

            'low_stock_items' => $allItems->filter(function ($item) {
                $activeCount = $item->itemInstances->where('status', 'active')->count();
                return $activeCount < $item->low_stock_threshold && $activeCount > 0;
            })->count(),

            'good_stock_items' => $allItems->filter(function ($item) {
                $activeCount = $item->itemInstances->where('status', 'active')->count();
                return $activeCount >= $item->low_stock_threshold;
            })->count(),

            'out_of_stock_items' => $allItems->filter(function ($item) {
                return $item->itemInstances->where('status', 'active')->count() == 0;
            })->count(),
        ];
        if ($request->has('filter')) {
            $filter = $request->input('filter');

            if ($filter === 'low_stock') {
                $query->whereHas('itemInstances', function ($q) {
                    $q->where('status', 'active');
                })
                    ->whereRaw('
                    (SELECT COUNT(*) FROM store_item_instances sii 
                     WHERE sii.store_item_id = store_items.id AND sii.status = "active")
                    < store_items.low_stock_threshold
                ')
                    ->whereRaw('
                    (SELECT COUNT(*) FROM store_item_instances sii 
                     WHERE sii.store_item_id = store_items.id AND sii.status = "active") > 0
                ');
            }

            if ($filter === 'good_stock') {
                $query->whereRaw('
                    (SELECT COUNT(*) FROM store_item_instances sii 
                     WHERE sii.store_item_id = store_items.id AND sii.status = "active")
                    >= store_items.low_stock_threshold
                ');
            }

            if ($filter === 'out_of_stock') {
                $query->whereRaw('
                    (SELECT COUNT(*) FROM store_item_instances sii 
                     WHERE sii.store_item_id = store_items.id AND sii.status = "active") = 0
                ');
            }
        }
        $storeItems = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $storeItems,
            'inventory_summary' => $inventorySummary,
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'images' => 'nullable|array',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|string|in:active,inactive,draft,archived',
        ]);

        // Generate SKU
        $sku = 'ITEM-' . strtoupper(substr(md5(uniqid()), 0, 8));

        // Generate slug
        $slug = \Illuminate\Support\Str::slug($validated['name']);

        // Handle image upload
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = Storage::disk('public')->putFile('store-items', $image);
                $imagePaths[] = $path;
            }
        }
        $user = $request->user();
        // Create store item
        try {
            $storeItem = StoreItems::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'sku' => $sku,
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? null,
                'images' => $imagePaths,
                'status' => $validated['status'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Store item created successfully',
                'slug' => $storeItem->slug,
            ]);
        } catch (Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ]);
        }
    }
    public function show(string $slug)
    {
        try {
            $storeItem = StoreItems::with('createdBy:name', 'updatedBy:name', 'itemInstances')->where('slug', $slug)->first();

            // Count instances by status
            $availableCount = $storeItem->itemInstances->where('status', 'active')->count();
            $reservedCount = $storeItem->itemInstances->where('status', 'reserved')->count();
            $usedCount = $storeItem->itemInstances->where('status', 'used')->count();

            // Add counts to the response
            $storeItem->available_count = $availableCount;
            $storeItem->reserved_count = $reservedCount;
            $storeItem->used_count = $usedCount;

            return response()->json([
                'status' => 'success',
                'data' => $storeItem,
            ]);
        } catch (Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ]);
        }
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $slug)
    {
        $storeItem = StoreItems::where('slug', $slug)->first();
        if (!$storeItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'Store item not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'images' => 'nullable|array',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'sometimes|required|string|in:active,inactive,draft,archived',
        ]);

        // Handle name/slug change
        if (isset($validated['name']) && $validated['name'] !== $storeItem->name) {
            $newSlug = Str::slug($validated['name']);
            $originalSlug = $newSlug;
            $counter = 1;
            while (StoreItems::where('slug', $newSlug)->where('id', '!=', $storeItem->id)->exists()) {
                $newSlug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $newSlug;
        }

        // Handle image upload
        if ($request->hasFile('images')) {
            $imagePaths = $storeItem->images ?? [];
            foreach ($request->file('images') as $image) {
                $path = Storage::disk('public')->putFile('store-items', $image);
                $imagePaths[] = $path;
            }
            $validated['images'] = $imagePaths;
        }

        $user = $request->user();
        $validated['updated_by'] = $user->id;

        try {
            $storeItem->update($validated);
            return response()->json([
                'status' => 'success',
                'message' => 'Store item updated successfully',
                'slug' => $storeItem->slug,
            ]);
        } catch (Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $slug)
    {
        $storeItem = StoreItems::where('slug', $slug)->first();
        if (!$storeItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'Store item not found',
            ], 404);
        }

        try {
            // Delete images from storage
            if ($storeItem->images && is_array($storeItem->images)) {
                foreach ($storeItem->images as $imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }
            }

            $storeItem->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Store item deleted successfully',
            ]);
        } catch (Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }
}
