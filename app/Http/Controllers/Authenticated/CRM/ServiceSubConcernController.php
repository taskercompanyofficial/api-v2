<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\ServiceSubConcern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceSubConcernController extends Controller
{
    /**
     * Display a listing of sub-concerns
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServiceSubConcern::with('concern');

        if ($request->filled('concern_id')) {
            $query->forConcern($request->concern_id);
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $subConcerns = $query->ordered()->get();

        return response()->json([
            'status' => 'success',
            'data' => $subConcerns
        ]);
    }

    /**
     * Get sub-concerns for a specific concern
     */
    public function getByConcern(string $concernId): JsonResponse
    {
        $subConcerns = ServiceSubConcern::active()
            ->forConcern($concernId)
            ->ordered()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $subConcerns
        ]);
    }

    /**
     * Store a newly created sub-concern
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_concern_id' => 'required|exists:service_concerns,id',
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100',
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'solution_guide' => 'nullable|string',
            'display_order' => 'nullable|integer',
            'is_active' => 'boolean'
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $subConcern = ServiceSubConcern::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Service sub-concern created successfully',
            'data' => $subConcern->load('concern')
        ], 201);
    }

    /**
     * Display the specified sub-concern
     */
    public function show(string $id): JsonResponse
    {
        $subConcern = ServiceSubConcern::with('concern')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $subConcern
        ]);
    }

    /**
     * Update the specified sub-concern
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $subConcern = ServiceSubConcern::findOrFail($id);

        $validated = $request->validate([
            'service_concern_id' => 'sometimes|exists:service_concerns,id',
            'name' => 'sometimes|string|max:100',
            'slug' => 'sometimes|string|max:100',
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'solution_guide' => 'nullable|string',
            'display_order' => 'nullable|integer',
            'is_active' => 'boolean'
        ]);

        $subConcern->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Service sub-concern updated successfully',
            'data' => $subConcern->fresh()->load('concern')
        ]);
    }

    /**
     * Remove the specified sub-concern
     */
    public function destroy(string $id): JsonResponse
    {
        $subConcern = ServiceSubConcern::findOrFail($id);
        $subConcern->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Service sub-concern deleted successfully'
        ]);
    }

    /**
     * Get service sub-concerns in raw format for SearchSelect component
     */
    public function serviceSubConcernsRaw(Request $request): JsonResponse
    {
        try {
            $searchQuery = $request->input('name');
            $concernId = $request->input('service_concern_id');

            $query = ServiceSubConcern::query()->where('is_active', true);

            // Filter by concern if provided
            if ($concernId) {
                $query->where('service_concern_id', $concernId);
            }

            if ($searchQuery) {
                $query->where('name', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('code', 'LIKE', "%{$searchQuery}%");
            }

            $subConcerns = $query->select('id', 'name', 'code', 'description')
                ->orderBy('display_order', 'asc')
                ->limit(50)
                ->get()
                ->map(function ($subConcern) {
                    return [
                        'value' => $subConcern->id,
                        'label' => $subConcern->code ? "{$subConcern->code} - {$subConcern->name}" : $subConcern->name,
                        'description' => $subConcern->description,
                        'badge' => $subConcern->code,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $subConcerns,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve service sub-concerns.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
