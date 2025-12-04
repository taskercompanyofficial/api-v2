<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductsController extends Controller
{
    use QueryFilterTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = Product::query()->with([
            'createdBy:id,name',
            'updatedBy:id,name',
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
        $products = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'status' => 'success',
            'data' => $products,
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
            'images' => 'nullable|array',
            'images.*' => 'string',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
            'status' => 'required|string|in:active,inactive,draft,archived',
        ]);

        // Generate slug
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $user = $request->user();

        // Create product
        try {
            $product = Product::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'images' => $validated['images'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Product created successfully',
                'slug' => $product->slug,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug)
    {
        $product = Product::with(['createdBy:id,name', 'updatedBy:id,name'])->where('slug', $slug)->first();
        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'data' => $product,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $slug)
    {
        $product = Product::where('slug', $slug)->first();
        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
            'status' => 'sometimes|required|string|in:active,inactive,draft,archived',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $product->name) {
            $newSlug = Str::slug($validated['name']);
            $original = $newSlug; $i = 1;
            while (Product::where('slug', $newSlug)->where('id', '!=', $product->id)->exists()) {
                $newSlug = $original . '-' . $i; $i++;
            }
            $validated['slug'] = $newSlug;
        }

        $user = $request->user();
        $validated['updated_by'] = $user->id;

        try {
            $product->update($validated);
            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'slug' => $product->slug,
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
        $product = Product::where('slug', $slug)->first();
        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], 404);
        }
        try {
            $product->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Product deleted successfully',
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }
}
