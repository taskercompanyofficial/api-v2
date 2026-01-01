<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Category;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class ServicesController extends Controller
{
    use QueryFilterTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = Service::query()->with([
            'createdBy:id,name',
            'updatedBy:id,name',
            'category:id,name,slug',
        ]);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'name',
            'slug',
            'status',
            'category_id',
            'created_at',
            'updated_at'
        ]);
        $services = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(
            [
                'status' => 'success',
                'data' => $services,
            ]
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
            'status' => 'required|string|in:active,inactive,draft,archived',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        // Generate slug
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;

        // Ensure unique slug
        while (Service::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $user = $request->user();

        // Create service
        try {
            $service = Service::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'image' => $validated['image'] ?? null,
                'images' => $validated['images'] ?? [],
                'tags' => $validated['tags'] ?? [],
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'],
                'category_id' => $validated['category_id'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Service created successfully',
                'slug' => $service->slug,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug)
    {
        $service = Service::with([
            'createdBy:id,name',
            'updatedBy:id,name',
            'category:id,name,slug,image'
        ])->where('slug', $slug)->first();

        if (!$service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service not found',
            ], 404);
        }

        try {
            return response()->json([
                'status' => 'success',
                'data' => $service,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $slug)
    {
        $service = Service::where('slug', $slug)->first();

        if (!$service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
            'status' => 'sometimes|required|string|in:active,inactive,draft,archived',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        // Generate new slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $service->name) {
            $slug = Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;

            while (Service::where('slug', $slug)->where('id', '!=', $service->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        $user = $request->user();
        $validated['updated_by'] = $user->id;

        try {
            $service->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Service updated successfully',
                'slug' => $service->slug,
            ]);
        } catch (\Exception $err) {
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
        $service = Service::where('slug', $slug)->first();

        if (!$service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service not found',
            ], 404);
        }

        try {
            $service->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Service deleted successfully',
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }
    public function webIndex()
    {
        $services = Service::with('parentServices')->select('id', 'name', 'slug','description', 'image',  'tags', 'status', 'created_at', 'updated_at')->where('status', 'active')->get();
        return response()->json([
            'status' => 'success',
            'data' => $services,
        ]);
    }
    public function webShow(string $slug)
    {
        $service = Service::with('parentServices')->where('slug', $slug)->first();
        return response()->json([
            'status' => 'success',
            'data' => $service,
        ]);
    }

    public function servicesRaw(Request $request)
    {
        try {
            $searchQuery = $request->input('name');
            $categoryId = $request->input('category_id');

            $query = Service::query()->where('status', 'active');

            // Filter by category if provided
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if ($searchQuery) {
                $query->where('name', 'LIKE', "%{$searchQuery}%");
            }

            $services = $query->select('id', 'name', 'description')
                ->limit(50)
                ->get()
                ->map(function ($service) {
                    return [
                        'value' => $service->id,
                        'label' => $service->name,
                        'description' => $service->description,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $services,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve services.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
