<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\AuthorizedBrand;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthorizedBrandsController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = AuthorizedBrand::query()->with(['createdBy:id,name', 'updatedBy:id,name']);

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
            'tariffs' => 'nullable|array',
            'tariffs.*' => 'numeric|min:0',
            'status' => 'required|string|in:active,inactive,draft',
            'is_authorized' => 'boolean',
            'is_available_for_warranty' => 'boolean',
            'has_free_installation_service' => 'boolean',
            'billing_date' => 'nullable|date',
            'logo_image' => 'nullable|string',
            'policy_image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp,gif',
            'jobsheet_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,pdf',
            'bill_format_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,pdf',
        ]);

        $slug = Str::slug($validated['name']);
        $original = $slug; $i = 1;
        while (AuthorizedBrand::where('slug', $slug)->exists()) { $slug = $original.'-'.$i; $i++; }

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $images[] = Storage::disk('public')->putFile('authorized-brands', $img);
            }
        } elseif (isset($validated['images']) && is_array($validated['images'])) {
            $images = $validated['images'];
        }

        $logo = $validated['logo_image'] ?? null;
        $policy = $validated['policy_image'] ?? null;
        if ($request->hasFile('logo_image')) {
            $logo = Storage::disk('public')->putFile('authorized-brands', $request->file('logo_image'));
        }
        if ($request->hasFile('policy_image')) {
            $policy = Storage::disk('public')->putFile('authorized-brands', $request->file('policy_image'));
        }

        $jobsheet = null;
        $bill = null;
        if ($request->hasFile('jobsheet_file')) {
            $jobsheet = Storage::disk('public')->putFile('authorized-brands', $request->file('jobsheet_file'));
        }
        if ($request->hasFile('bill_format_file')) {
            $bill = Storage::disk('public')->putFile('authorized-brands', $request->file('bill_format_file'));
        }

        $user = $request->user();

        $brand = AuthorizedBrand::create([
            ...$validated,
            'slug' => $slug,
            'images' => $images,
            'logo_image' => $logo,
            'policy_image' => $policy,
            'jobsheet_file' => $jobsheet,
            'bill_format_file' => $bill,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Brand created successfully', 'slug' => $brand->slug]);
    }

    public function show(string $slug)
    {
        $brand = AuthorizedBrand::with(['createdBy:id,name', 'updatedBy:id,name'])->where('slug', $slug)->first();
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
            'tariffs' => 'nullable|array',
            'tariffs.*' => 'numeric|min:0',
            'status' => 'sometimes|required|string|in:active,inactive,draft',
            'is_authorized' => 'boolean',
            'is_available_for_warranty' => 'boolean',
            'has_free_installation_service' => 'boolean',
            'billing_date' => 'nullable|date',
            'logo_image' => 'nullable|string',
            'policy_image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp,gif',
            'jobsheet_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,pdf',
            'bill_format_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,pdf',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $brand->name) {
            $newSlug = Str::slug($validated['name']);
            $original = $newSlug; $i = 1;
            while (AuthorizedBrand::where('slug', $newSlug)->where('id', '!=', $brand->id)->exists()) { $newSlug = $original.'-'.$i; $i++; }
            $validated['slug'] = $newSlug;
        }

        $images = $brand->images ?? [];
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $img) {
                $images[] = Storage::disk('public')->putFile('authorized-brands', $img);
            }
        } elseif (isset($validated['images']) && is_array($validated['images'])) {
            $images = $validated['images'];
        }
        $validated['images'] = $images;

        if ($request->hasFile('logo_image')) {
            $validated['logo_image'] = Storage::disk('public')->putFile('authorized-brands', $request->file('logo_image'));
        }
        if ($request->hasFile('policy_image')) {
            $validated['policy_image'] = Storage::disk('public')->putFile('authorized-brands', $request->file('policy_image'));
        }
        if ($request->hasFile('jobsheet_file')) {
            $validated['jobsheet_file'] = Storage::disk('public')->putFile('authorized-brands', $request->file('jobsheet_file'));
        }
        if ($request->hasFile('bill_format_file')) {
            $validated['bill_format_file'] = Storage::disk('public')->putFile('authorized-brands', $request->file('bill_format_file'));
        }

        $user = $request->user();
        $validated['updated_by'] = $user->id;

        $brand->update($validated);
        return response()->json(['status' => 'success', 'message' => 'Brand updated successfully', 'slug' => $brand->slug]);
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