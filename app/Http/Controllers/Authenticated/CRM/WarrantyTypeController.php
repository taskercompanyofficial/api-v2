<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\WarrantyType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WarrantyTypeController extends Controller
{
    /**
     * Display a listing of warranty types
     */
    public function index(Request $request)
    {
        $query = WarrantyType::query();

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sort
        $sortBy = $request->get('sortBy', 'display_order');
        $sortOrder = $request->get('sortOrder', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->get('perPage', 15);
        $warrantyTypes = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $warrantyTypes,
        ]);
    }

    /**
     * Get warranty types in raw format for SearchSelect component
     */
    public function warrantyTypesRaw(Request $request)
    {
        try {
            $searchQuery = $request->input('name');

            $query = WarrantyType::query()->where('is_active', true);

            if ($searchQuery) {
                $query->where('name', 'LIKE', "%{$searchQuery}%");
            }

            $warrantyTypes = $query->select('id', 'name', 'description', 'icon', 'color')
                ->orderBy('display_order', 'asc')
                ->limit(50)
                ->get()
                ->map(function ($type) {
                    return [
                        'value' => $type->id,
                        'label' => $type->name,
                        'description' => $type->description,
                        'badge' => $type->icon,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $warrantyTypes,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve warranty types.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }

    /**
     * Store a newly created warranty type
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:warranty_types,name',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:7',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Generate slug
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;

        while (WarrantyType::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $validated['slug'] = $slug;
        $validated['display_order'] = $validated['display_order'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $warrantyType = WarrantyType::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Warranty type created successfully',
            'data' => $warrantyType,
        ], 201);
    }

    /**
     * Display the specified warranty type
     */
    public function show($id)
    {
        $warrantyType = WarrantyType::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $warrantyType,
        ]);
    }

    /**
     * Update the specified warranty type
     */
    public function update(Request $request, $id)
    {
        $warrantyType = WarrantyType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:warranty_types,name,' . $id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:7',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Generate new slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $warrantyType->name) {
            $slug = Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;

            while (WarrantyType::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        $warrantyType->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Warranty type updated successfully',
            'data' => $warrantyType,
        ]);
    }

    /**
     * Remove the specified warranty type
     */
    public function destroy($id)
    {
        $warrantyType = WarrantyType::findOrFail($id);
        $warrantyType->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Warranty type deleted successfully',
        ]);
    }
}
