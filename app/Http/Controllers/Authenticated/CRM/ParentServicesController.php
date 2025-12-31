<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\ParentServices;
use App\Models\ServiceRequiredFile;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ParentServicesController extends Controller
{
    use QueryFilterTrait;

    /**
     * Display a listing of the parent services.
     */
    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = ParentServices::query()->with([
            'createdBy:id,name',
            'updatedBy:id,name',
            'service:id,name,slug',
            'requiredFileTypes.fileType',
        ]);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'name',
            'slug',
            'status',
            'service_id',
            'created_at',
            'updated_at'
        ]);
        $parentServices = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(
            [
                'status' => 'success',
                'data' => $parentServices,
            ]
        );
    }

    /**
     * Store a newly created parent service in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|string|in:percentage,fixed',
            'discount_start_date' => 'nullable|date',
            'discount_end_date' => 'nullable|date|after_or_equal:discount_start_date',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'includes' => 'nullable|array',
            'includes.*' => 'string',
            'excludes' => 'nullable|array',
            'excludes.*' => 'string',
            'notes' => 'nullable|string',
            'status' => 'required|string|in:active,inactive,draft,archived',
            'required_file_types' => 'nullable|array',
            'required_file_types.*.file_type_id' => 'required|exists:file_types,id',
            'required_file_types.*.is_required' => 'required|boolean',
        ]);

        // Generate slug
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;

        // Ensure unique slug
        while (ParentServices::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $user = $request->user();

        try {
            $parentService = ParentServices::create([
                'service_id' => $validated['service_id'],
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'image' => $validated['image'] ?? null,
                'images' => $validated['images'] ?? [],
                'price' => $validated['price'],
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? 'percentage',
                'discount_start_date' => $validated['discount_start_date'] ?? null,
                'discount_end_date' => $validated['discount_end_date'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'includes' => $validated['includes'] ?? [],
                'excludes' => $validated['excludes'] ?? [],
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Sync required file types
            if (isset($validated['required_file_types'])) {
                foreach ($validated['required_file_types'] as $fileType) {
                    ServiceRequiredFile::create([
                        'parent_service_id' => $parentService->id,
                        'file_type_id' => $fileType['file_type_id'],
                        'is_required' => $fileType['is_required'],
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Parent Service created successfully',
                'slug' => $parentService->slug,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified parent service.
     */
    public function show(string $slug)
    {
        $parentService = ParentServices::with([
            'createdBy:id,name',
            'updatedBy:id,name',
            'service:id,name,slug,image',
            'requiredFileTypes.fileType',
        ])->where('slug', $slug)->first();

        if (!$parentService) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parent Service not found',
            ], 404);
        }

        try {
            return response()->json([
                'status' => 'success',
                'data' => $parentService,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified parent service in storage.
     */
    public function update(Request $request, string $slug)
    {
        $parentService = ParentServices::where('slug', $slug)->first();

        if (!$parentService) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parent Service not found',
            ], 404);
        }

        $validated = $request->validate([
            'service_id' => 'sometimes|required|exists:services,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'price' => 'sometimes|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|string|in:percentage,fixed',
            'discount_start_date' => 'nullable|date',
            'discount_end_date' => 'nullable|date|after_or_equal:discount_start_date',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'includes' => 'nullable|array',
            'includes.*' => 'string',
            'excludes' => 'nullable|array',
            'excludes.*' => 'string',
            'notes' => 'nullable|string',
            'status' => 'sometimes|required|string|in:active,inactive,draft,archived',
            'required_file_types' => 'nullable|array',
            'required_file_types.*.file_type_id' => 'required|exists:file_types,id',
            'required_file_types.*.is_required' => 'required|boolean',
        ]);

        // Generate new slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $parentService->name) {
            $slug = Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;

            while (ParentServices::where('slug', $slug)->where('id', '!=', $parentService->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        $user = $request->user();
        $validated['updated_by'] = $user->id;

        try {
            $parentService->update($validated);

            // Sync required file types
            if (isset($validated['required_file_types'])) {
                // Delete existing
                ServiceRequiredFile::where('parent_service_id', $parentService->id)->delete();
                
                // Create new
                foreach ($validated['required_file_types'] as $fileType) {
                    ServiceRequiredFile::create([
                        'parent_service_id' => $parentService->id,
                        'file_type_id' => $fileType['file_type_id'],
                        'is_required' => $fileType['is_required'],
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Parent Service updated successfully',
                'slug' => $parentService->slug,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified parent service from storage.
     */
    public function destroy(string $slug)
    {
        $parentService = ParentServices::where('slug', $slug)->first();

        if (!$parentService) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parent Service not found',
            ], 404);
        }

        try {
            $parentService->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Parent Service deleted successfully',
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }
    public function parentServices(Request $request)
    {
 try {
        $parentServices = ParentServices::get();

        return response()->json(
            [
                'status' => 'success',
                'data' => $parentServices,
            ]
        );
    } catch (\Exception $err) {
        return response()->json([
            'status' => 'error',
            'message' => $err->getMessage(),
        ], 500);
    }
    }
}
