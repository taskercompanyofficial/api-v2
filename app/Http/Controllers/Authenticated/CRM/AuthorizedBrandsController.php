<?php


namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\AuthorizedBrand;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthorizedBrandsController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('perPage', 10);

        $query = AuthorizedBrand::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name']);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'name', 'slug', 'status', 'service_type', 'billing_date', 'created_at', 'updated_at'
        ]);

        $brands = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $brands]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'service_type' => 'nullable|string',
            'status' => 'required|string|in:active,inactive,draft',
            'is_authorized' => 'boolean',
            'is_available_for_warranty' => 'boolean',
            'has_free_installation_service' => 'boolean',
            'billing_date' => 'nullable|date',
            'logo_image' => 'nullable|string',
            // Images array (string URLs/paths only)
            'images' => 'nullable|array',
            'images.*' => 'string',
            // Documents array (frontend multi-doc logic)
            'documents' => 'nullable|array',
            'documents.*.type' => 'required_with:documents|string',
            'documents.*.file' => 'required_with:documents|string',
            // Warranty/service/material arrays (optional, handled as json)
            'warranty_parts' => 'nullable|array',
            'service_charges' => 'nullable|array',
            'materials' => 'nullable|array',
        ]);

        // Generate unique slug
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug; $i = 1;
        while (AuthorizedBrand::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $i; $i++;
        }

        // Use logo image URL directly, no file handling
        $logoImage = $validated['logo_image'] ?? null;

        // Only URLs for images, no file upload
        $images = $validated['images'] ?? [];

        // Handle documents (combine into expected array)
        $documents = $validated['documents'] ?? [];

        // These attributes default to [] in the BrandForm React (see context)
        $warrantyParts = $validated['warranty_parts'] ?? [];
        $serviceCharges = $validated['service_charges'] ?? [];
        $materials = $validated['materials'] ?? [];

        $user = $request->user();

        $brand = AuthorizedBrand::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'service_type' => $validated['service_type'] ?? null,
            'status' => $validated['status'],
            'is_authorized' => $validated['is_authorized'] ?? true,
            'is_available_for_warranty' => $validated['is_available_for_warranty'] ?? false,
            'has_free_installation_service' => $validated['has_free_installation_service'] ?? false,
            'billing_date' => $validated['billing_date'] ?? null,
            'logo_image' => $logoImage,
            'images' => $images,
            'documents' => $documents,
            'warranty_parts' => $warrantyParts,
            'service_charges' => $serviceCharges,
            'materials' => $materials,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Brand created successfully',
            'slug' => $brand->slug,
        ]);
    }

    public function show(string $slug)
    {
        $brand = AuthorizedBrand::with(['createdBy:id,name', 'updatedBy:id,name'])
            ->where('slug', $slug)->first();
        if (!$brand) {
            return response()->json(['status' => 'error', 'message' => 'Brand not found'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $brand]);
    }

    public function update(Request $request, string $slug)
    {
        $brand = AuthorizedBrand::where('slug', $slug)->first();
        if (!$brand) {
            return response()->json(['status' => 'error', 'message' => 'Brand not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'service_type' => 'nullable|string',
            'status' => 'sometimes|required|string|in:active,inactive,draft',
            'is_authorized' => 'boolean',
            'is_available_for_warranty' => 'boolean',
            'has_free_installation_service' => 'boolean',
            'billing_date' => 'nullable|date',
            'logo_image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'documents' => 'nullable|array',
            'documents.*.type' => 'required_with:documents|string',
            'documents.*.file' => 'required_with:documents|string',
            'warranty_parts' => 'nullable|array',
            'service_charges' => 'nullable|array',
            'materials' => 'nullable|array',
        ]);

        // Handle slug update if name changes
        if (isset($validated['name']) && $validated['name'] !== $brand->name) {
            $newSlug = Str::slug($validated['name']);
            $original = $newSlug; $i = 1;
            while (AuthorizedBrand::where('slug', $newSlug)->where('id', '!=', $brand->id)->exists()) {
                $newSlug = $original . '-' . $i; $i++;
            }
            $validated['slug'] = $newSlug;
        }

        // Use logo image URL directly, no file handling
        // Only URLs for images
        if (isset($validated['images']) && is_array($validated['images'])) {
            $validated['images'] = array_filter($validated['images'], fn($val) => is_string($val));
        } else {
            $validated['images'] = $brand->images ?? [];
        }

        // Compose documents array for consistent structure
        if (isset($validated['documents']) && is_array($validated['documents'])) {
            $validated['documents'] = array_values($validated['documents']);
        } else {
            $validated['documents'] = $brand->documents ?? [];
        }

        // Arrays for warranty, service, materials
        $validated['warranty_parts'] = $validated['warranty_parts'] ?? $brand->warranty_parts ?? [];
        $validated['service_charges'] = $validated['service_charges'] ?? $brand->service_charges ?? [];
        $validated['materials'] = $validated['materials'] ?? $brand->materials ?? [];

        $user = $request->user();
        $validated['updated_by'] = $user->id;

        $brand->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Brand updated successfully',
            'slug' => $brand->slug
        ]);
    }

    public function destroy(string $slug)
    {
        $brand = AuthorizedBrand::where('slug', $slug)->first();
        if (!$brand) {
            return response()->json(['status' => 'error', 'message' => 'Brand not found'], 404);
        }
        $brand->delete();
        return response()->json(['status' => 'success', 'message' => 'Brand deleted successfully']);
    }
}
