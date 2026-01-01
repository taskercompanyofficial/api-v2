<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class CategoriesController extends Controller
{
    use QueryFilterTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = Categories::query()->with([
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'name',
            'slug',
            'status',
            'created_at',
            'updated_at',
        ]);

        $categories = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'status' => 'required|in:active,inactive,draft,archived',
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;

        while (Categories::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $user = $request->user();

        $category = Categories::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'image' => $validated['image'] ?? null,
            'images' => $validated['images'] ?? [],
            'tags' => $validated['tags'] ?? [],
            'status' => $validated['status'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Category created successfully',
            'slug' => $category->slug,
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug)
    {
        $category = Categories::with([
            'createdBy:id,name',
            'updatedBy:id,name',
        ])->where('slug', $slug)->first();

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $category,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $slug)
    {
        $category = Categories::where('slug', $slug)->first();

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'status' => 'required|in:active,inactive,draft,archived',
        ]);

        $newSlug = $category->slug;
        if (($validated['name'] ?? $category->name) !== $category->name) {
            $newSlug = Str::slug($validated['name']);
            $originalSlug = $newSlug;
            $counter = 1;

            while (Categories::where('slug', $newSlug)->where('id', '!=', $category->id)->exists()) {
                $newSlug = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $user = $request->user();

        $category->update([
            'name' => $validated['name'],
            'slug' => $newSlug,
            'description' => $validated['description'] ?? null,
            'image' => $validated['image'] ?? null,
            'images' => $validated['images'] ?? [],
            'tags' => $validated['tags'] ?? [],
            'status' => $validated['status'],
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'slug' => $category->slug,
            'data' => $category,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $slug)
    {
        $category = Categories::where('slug', $slug)->first();

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found',
            ], 404);
        }

        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully',
        ]);
    }
    public function webIndex()
    {
        $categories = Categories::select('id', 'name', 'slug','description', 'image',  'tags', 'status', 'created_at', 'updated_at')->where('status', 'active')->get();
        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }
    public function webShow(string $slug)
    {
        $category = Categories::with('services')->where('slug', $slug)->first();
        return response()->json([
            'status' => 'success',
            'data' => $category,
        ]);
    }

    public function categoriesRaw(Request $request)
    {
        try {
            $searchQuery = $request->input('name');

            $query = Categories::query()->where('status', 'active');

            if ($searchQuery) {
                $query->where('name', 'LIKE', "%{$searchQuery}%");
            }

            $categories = $query->select('id', 'name', 'description')
                ->limit(50)
                ->get()
                ->map(function ($category) {
                    return [
                        'value' => $category->id,
                        'label' => $category->name,
                        'description' => $category->description,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $categories,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve categories.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
