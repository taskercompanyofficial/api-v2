<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\OurBranch;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OurBranchesController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;

        $query = OurBranch::query()->with(['createdBy:id,name', 'updatedBy:id,name', 'manager:id,name']);

        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);

        $this->applyUrlFilters($query, $request, [
            'name', 'slug', 'city', 'state', 'status', 'branch_designation', 'created_at', 'updated_at'
        ]);

        $branches = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $branches]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string',
            'whatsapp' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
            'branch_designation' => 'nullable|string',
            'status' => 'required|string|in:active,inactive,pending',
            'visible_to_customers' => 'required|string|in:yes,no',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp,gif',
            'product_services' => 'nullable|array',
            'opening_hours' => 'nullable|array',
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug; $i = 1;
        while (OurBranch::where('slug', $slug)->exists()) { $slug = $originalSlug.'-'.$i; $i++; }

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $images[] = Storage::disk('public')->putFile('our-branches', $img);
            }
        } elseif (isset($validated['images']) && is_array($validated['images'])) {
            $images = $validated['images'];
        }

        $user = $request->user();

        $branch = OurBranch::create([
            ...$validated,
            'slug' => $slug,
            'images' => $images,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Branch created successfully',
            'slug' => $branch->slug,
        ]);
    }

    public function show(string $slug)
    {
        $branch = OurBranch::with(['createdBy:id,name', 'updatedBy:id,name', 'manager:id,name'])->where('slug', $slug)->first();
        if (!$branch) {
            return response()->json(['status' => 'error', 'message' => 'Branch not found'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $branch]);
    }

    public function update(Request $request, string $slug)
    {
        $branch = OurBranch::where('slug', $slug)->first();
        if (!$branch) {
            return response()->json(['status' => 'error', 'message' => 'Branch not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string',
            'whatsapp' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
            'branch_designation' => 'nullable|string',
            'status' => 'sometimes|required|string|in:active,inactive,pending',
            'visible_to_customers' => 'required|string|in:yes,no',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp,gif',
            'product_services' => 'nullable|array',
            'opening_hours' => 'nullable|array',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $branch->name) {
            $newSlug = Str::slug($validated['name']);
            $original = $newSlug; $i = 1;
            while (OurBranch::where('slug', $newSlug)->where('id', '!=', $branch->id)->exists()) {
                $newSlug = $original.'-'.$i; $i++;
            }
            $validated['slug'] = $newSlug;
        }

        $images = $branch->images ?? [];
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $img) {
                $images[] = Storage::disk('public')->putFile('our-branches', $img);
            }
        } elseif (isset($validated['images']) && is_array($validated['images'])) {
            $images = $validated['images'];
        }
        $validated['images'] = $images;

        $user = $request->user();
        $validated['updated_by'] = $user->id;

        $branch->update($validated);
        return response()->json([
            'status' => 'success',
            'message' => 'Branch updated successfully',
            'slug' => $branch->slug,
        ]);
    }

    public function destroy(string $slug)
    {
        $branch = OurBranch::where('slug', $slug)->first();
        if (!$branch) {
            return response()->json(['status' => 'error', 'message' => 'Branch not found'], 404);
        }
        $branch->delete();
        return response()->json(['status' => 'success', 'message' => 'Branch deleted successfully']);
    }
}