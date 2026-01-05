<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\DealerBranch;
use App\QueryFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class DealerBranchesController extends Controller
{
    use QueryFilterTrait;

    /**
     * Display a listing of dealer branches.
     */
    public function index(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $perPage = $request->input('perPage', 10);

            $query = DealerBranch::query()->with([
                'dealer:id,name,slug',
                'createdBy:id,name',
                'updatedBy:id,name',
            ]);

            $this->applyJsonFilters($query, $request);
            $this->applySorting($query, $request);

            $this->applyUrlFilters($query, $request, [
                'name',
                'branch_code',
                'phone',
                'email',
                'city',
                'state',
                'status',
                'is_main_branch',
                'dealer_id',
                'created_at',
                'updated_at',
            ]);

            $branches = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'data' => $branches,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dealer branches.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created dealer branch.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'dealer_id' => 'required|exists:dealers,id',
                'name' => 'required|string|max:255',
                'branch_designation' => 'nullable|string|max:100',
                'phone' => 'required|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'contact_person_name' => 'nullable|string|max:255',
                'contact_person_phone' => 'nullable|string|max:20',
                'address_line_1' => 'nullable|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'zip_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:100',
                'area_type' => 'nullable|in:residential,commercial,industrial,other',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'products_handled' => 'nullable|array',
                'service_areas' => 'nullable|array',
                'monthly_target' => 'nullable|numeric|min:0',
                'opening_hours' => 'nullable|array',
                'image' => 'nullable|string|max:255',
                'images' => 'nullable|array',
                'status' => 'nullable|in:active,inactive,temporarily_closed',
                'is_main_branch' => 'nullable|boolean',
                'visible_to_customers' => 'nullable|boolean',
                'notes' => 'nullable|string',
            ]);

            // Generate unique branch code
            $dealer = Dealer::findOrFail($validatedData['dealer_id']);
            $dealerPrefix = strtoupper(substr($dealer->slug, 0, 3));
            $branchNumber = DealerBranch::where('dealer_id', $dealer->id)->count() + 1;
            $branchCode = $dealerPrefix . '-BR-' . str_pad($branchNumber, 3, '0', STR_PAD_LEFT);

            while (DealerBranch::where('branch_code', $branchCode)->exists()) {
                $branchNumber++;
                $branchCode = $dealerPrefix . '-BR-' . str_pad($branchNumber, 3, STR_PAD_LEFT);
            }

            $slug = Str::slug($validatedData['name'] . '-' . $dealer->slug);
            $originalSlug = $slug;
            $counter = 1;

            while (DealerBranch::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $validatedData['slug'] = $slug;
            $validatedData['branch_code'] = $branchCode;
            $validatedData['created_by'] = Auth::id();
            $validatedData['updated_by'] = Auth::id();

            $branch = DealerBranch::create($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Dealer branch created successfully.',
                'data' => $branch->load(['dealer', 'createdBy:id,name', 'updatedBy:id,name']),
                'slug' => $branch->slug,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create dealer branch.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified dealer branch.
     */
    public function show(string $slug)
    {
        try {
            $branch = DealerBranch::with([
                'dealer',
                'createdBy:id,name',
                'updatedBy:id,name',
            ])->where('slug', $slug)->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data' => $branch,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dealer branch not found.',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified dealer branch.
     */
    public function update(Request $request, string $slug)
    {
        try {
            $branch = DealerBranch::where('slug', $slug)->firstOrFail();

            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'branch_designation' => 'nullable|string|max:100',
                'phone' => 'required|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'contact_person_name' => 'nullable|string|max:255',
                'contact_person_phone' => 'nullable|string|max:20',
                'address_line_1' => 'nullable|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'zip_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:100',
                'area_type' => 'nullable|in:residential,commercial,industrial,other',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'products_handled' => 'nullable|array',
                'service_areas' => 'nullable|array',
                'monthly_target' => 'nullable|numeric|min:0',
                'opening_hours' => 'nullable|array',
                'image' => 'nullable|string|max:255',
                'images' => 'nullable|array',
                'status' => 'nullable|in:active,inactive,temporarily_closed',
                'is_main_branch' => 'nullable|boolean',
                'visible_to_customers' => 'nullable|boolean',
                'notes' => 'nullable|string',
            ]);

            // Update slug if name changed
            if ($validatedData['name'] !== $branch->name) {
                $dealer = $branch->dealer;
                $slug = Str::slug($validatedData['name'] . '-' . $dealer->slug);
                $originalSlug = $slug;
                $counter = 1;

                while (DealerBranch::where('slug', $slug)->where('id', '!=', $branch->id)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
                $validatedData['slug'] = $slug;
            }

            $validatedData['updated_by'] = Auth::id();
            $branch->update($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Dealer branch updated successfully.',
                'data' => $branch->fresh(['dealer', 'createdBy:id,name', 'updatedBy:id,name']),
                'slug' => $branch->slug,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update dealer branch.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified dealer branch.
     */
    public function destroy(string $slug)
    {
        try {
            $branch = DealerBranch::where('slug', $slug)->firstOrFail();
            $branch->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Dealer branch deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete dealer branch.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function dealerBranchesRaw(Request $request)
    {
        try {
            $searchQuery = $request->input('name');
            $dealerId = $request->input('dealer_id');

            $query = DealerBranch::query()->where('status', 'active');

            if ($dealerId) {
                $query->where('dealer_id', $dealerId);
            }

            if ($searchQuery) {
                $query->where('name', 'LIKE', "%{$searchQuery}%");
            }

            $branches = $query->select('id', 'name', 'branch_code', 'city')
                ->limit(50)
                ->get()
                ->map(function ($branch) {
                    return [
                        'value' => $branch->id,
                        'label' => $branch->name . ($branch->branch_code ? " - {$branch->branch_code}" : "") . ($branch->city ? " ({$branch->city})" : ""),
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $branches,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dealer branches.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
