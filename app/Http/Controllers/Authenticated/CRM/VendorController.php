<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\QueryFilterTrait;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    use QueryFilterTrait;

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;
        $query = Vendor::query();
        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);
        $this->applyUrlFilters($query, $request, ['name', 'phone', 'email', 'status']);
        $vendors = $query->paginate($perPage, ['*'], 'page', $page);

        // Let's add computed map for the frontend which expects arrays similar to other resources
        $vendors->getCollection()->transform(function ($vendor) {
            return [
                'id' => $vendor->id,
                'slug' => $vendor->slug,
                'name' => $vendor->name, // Keeping for backward compatibility
                'first_name' => $vendor->first_name,
                'last_name' => $vendor->last_name,
                'company_name' => $vendor->company_name,
                'email' => $vendor->email,
                'phone' => $vendor->phone,
                'status_id' => $vendor->status,
                'status' => ['name' => $vendor->status],
                'created_at' => $vendor->created_at,
                'updated_at' => $vendor->updated_at,
                // balance logic can be added later as part of Vendor Ledger
                'balance' => 0,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $vendors]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'company_name' => 'required|string|max:255',
            'cnic' => 'nullable|string|max:20|unique:vendors,cnic',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string',
            'phone' => 'required|string|unique:vendors,phone',
            'email' => 'nullable|email|unique:vendors,email',
            'password' => 'nullable|string|min:6',
            'status' => 'nullable|in:active,inactive',
            'profile_image' => 'nullable|string',
            'cnic_front_image' => 'nullable|string',
            'cnic_back_image' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'joining_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            $validated['password'] = bcrypt('12345678'); // Default password if empty
        }

        if (!isset($validated['status'])) {
            $validated['status'] = 'active';
        }

        // Create slug based on company name or user name
        $nameToSlug = $validated['company_name'] ?? ($validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));
        $slug = \Illuminate\Support\Str::slug(trim($nameToSlug));
        $original = $slug;
        $i = 1;
        while (Vendor::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $i;
            $i++;
        }
        $validated['slug'] = $slug;
        $validated['name'] = $validated['company_name']; // Ensure required 'name' from previous iteration

        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $vendor = Vendor::create($validated);

        return response()->json(['status' => 'success', 'message' => 'Vendor created successfully', 'id' => $vendor->id, 'slug' => $vendor->slug]);
    }

    public function show(string $id)
    {
        // Check by slug or ID
        $vendor = Vendor::where('slug', $id)->first() ?? Vendor::find($id);
        if (!$vendor) {
            return response()->json(['status' => 'error', 'message' => 'Vendor not found'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $vendor]);
    }

    public function update(Request $request, string $id)
    {
        // Try to find by slug or ID
        $vendor = Vendor::where('slug', $id)->first() ?? Vendor::find($id);
        if (!$vendor) {
            return response()->json(['status' => 'error', 'message' => 'Vendor not found'], 404);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'company_name' => 'sometimes|required|string|max:255',
            'cnic' => 'nullable|string|max:20|unique:vendors,cnic,' . $vendor->id,
            'dob' => 'nullable|date',
            'gender' => 'nullable|string',
            'phone' => 'sometimes|required|string|unique:vendors,phone,' . $vendor->id,
            'email' => 'nullable|email|unique:vendors,email,' . $vendor->id,
            'password' => 'nullable|string|min:6',
            'status' => 'nullable|in:active,inactive',
            'profile_image' => 'nullable|string',
            'cnic_front_image' => 'nullable|string',
            'cnic_back_image' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'joining_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        if (isset($validated['company_name'])) {
            $validated['name'] = $validated['company_name'];
        }

        $validated['updated_by'] = $request->user()->id;

        $vendor->update($validated);
        return response()->json(['status' => 'success', 'message' => 'Vendor updated successfully', 'id' => $vendor->id, 'slug' => $vendor->slug]);
    }

    public function destroy(string $id)
    {
        $vendor = Vendor::find($id);
        if (!$vendor) {
            return response()->json(['status' => 'error', 'message' => 'Vendor not found'], 404);
        }
        $vendor->delete();
        return response()->json(['status' => 'success', 'message' => 'Vendor deleted successfully']);
    }

    public function vendorsRaw(Request $request)
    {
        try {
            $searchQuery = $request->input('name');
            $status = $request->input('status');

            $query = Vendor::query();

            if ($status) {
                $query->where('status', $status);
            }

            if ($searchQuery) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('name', 'LIKE', "%{$searchQuery}%")
                        ->orWhere('phone', 'LIKE', "%{$searchQuery}%")
                        ->orWhere('email', 'LIKE', "%{$searchQuery}%");
                });
            }

            $vendors = $query->select('id', 'name', 'email', 'phone')
                ->limit(50)
                ->get()
                ->map(function ($vendor) {
                    return [
                        'value' => $vendor->id,
                        'label' => $vendor->name . ($vendor->email ? " ({$vendor->email})" : "") . " - {$vendor->phone}",
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $vendors,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve vendors.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
