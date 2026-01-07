<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\ServiceConcern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceConcernController extends Controller
{
    /**
     * Display a listing of service concerns
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServiceConcern::with('parentService');

        // Filter by parent service if provided
        if ($request->filled('parent_service_id')) {
            $query->forParentService($request->parent_service_id);
        }

        // Filter active only if requested
        if ($request->boolean('active_only')) {
            $query->active();
        }

        $concerns = $query->ordered()->get();

        return response()->json([
            'status' => 'success',
            'data' => $concerns
        ]);
    }

    /**
     * Get concerns for a specific parent service
     */
    public function getByParentService(string $parentServiceId): JsonResponse
    {
        $concerns = ServiceConcern::active()
            ->forParentService($parentServiceId)
            ->with('subConcerns')
            ->ordered()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $concerns
        ]);
    }

    /**
     * Store a newly created concern
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_service_id' => 'required|exists:parent_services,id',
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'display_order' => 'nullable|integer',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean'
        ]);

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $concern = ServiceConcern::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Service concern created successfully',
            'data' => $concern->load('parentService')
        ], 201);
    }

    /**
     * Display the specified concern
     */
    public function show(string $id): JsonResponse
    {
        $concern = ServiceConcern::with(['parentService', 'subConcerns'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $concern
        ]);
    }

    /**
     * Update the specified concern
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $concern = ServiceConcern::findOrFail($id);

        $validated = $request->validate([
            'parent_service_id' => 'sometimes|exists:parent_services,id',
            'name' => 'sometimes|string|max:100',
            'slug' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'display_order' => 'nullable|integer',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean'
        ]);

        $concern->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Service concern updated successfully',
            'data' => $concern->fresh()->load('parentService')
        ]);
    }

    /**
     * Remove the specified concern
     */
    public function destroy(string $id): JsonResponse
    {
        $concern = ServiceConcern::findOrFail($id);
        $concern->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Service concern deleted successfully'
        ]);
    }

    /**
     * Get service concerns in raw format for SearchSelect component
     */
    public function serviceConcernsRaw(Request $request): JsonResponse
    {
        try {
            $searchQuery = $request->input('name');
            $parentServiceId = $request->input('parent_service_id');

            $query = ServiceConcern::query()->where('is_active', true);

            // Filter by parent service if provided
            if ($parentServiceId) {
                $query->where('parent_service_id', $parentServiceId);
            }

            if ($searchQuery) {
                $query->where('name', 'LIKE', "%{$searchQuery}%");
            }

            $concerns = $query->select('id', 'name', 'description')
                ->orderBy('display_order', 'asc')
                ->limit(50)
                ->get()
                ->map(function ($concern) {
                    return [
                        'value' => $concern->id,
                        'label' => $concern->name,
                        'description' => $concern->description,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $concerns,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve service concerns.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
