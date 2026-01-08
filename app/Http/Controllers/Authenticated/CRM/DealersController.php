<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\QueryFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class DealersController extends Controller
{
    use QueryFilterTrait;

    /**
     * Display a listing of the dealers.
     */
    public function index(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $perPage = $request->input('perPage', 10);

            $query = Dealer::query()->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'branches' => function ($query) {
                    $query->where('status', 'active')->orderBy('is_main_branch', 'desc');
                }
            ]);

            $this->applyJsonFilters($query, $request);
            $this->applySorting($query, $request);

            $this->applyUrlFilters($query, $request, [
                'name',
                'business_type',
                'phone',
                'email',
                'city',
                'state',
                'status',
                'is_verified',
                'created_at',
                'updated_at',
            ]);

            $dealers = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'data' => $dealers,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dealers.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created dealer.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'business_type' => 'nullable|string|max:100',
                'license_number' => 'nullable|string|max:100',
                'registration_number' => 'nullable|string|max:100',
                'phone' => 'required|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255|unique:dealers,email',
                'owner_name' => 'nullable|string|max:255',
                'owner_phone' => 'nullable|string|max:20',
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
                'credit_limit' => 'nullable|numeric|min:0',
                'agreement_start_date' => 'nullable|date',
                'agreement_end_date' => 'nullable|date|after:agreement_start_date',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'logo' => 'nullable|string|max:255',
                'images' => 'nullable|array',
                'documents' => 'nullable|array',
                'status' => 'nullable|in:active,inactive,suspended,pending_approval',
                'is_verified' => 'nullable|boolean',
                'can_create_branches' => 'nullable|boolean',
                'notes' => 'nullable|string',
            ]);

            $slug = Str::slug($validatedData['name']);
            $originalSlug = $slug;
            $counter = 1;

            while (Dealer::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $validatedData['slug'] = $slug;
            $validatedData['created_by'] = Auth::id();
            $validatedData['updated_by'] = Auth::id();

            $dealer = Dealer::create($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Dealer created successfully.',
                'data' => $dealer,
                'slug' => $dealer->slug,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create dealer.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified dealer.
     */
    public function show(string $slug)
    {
        try {
            $dealer = Dealer::with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'branches' => function ($query) {
                    $query->orderBy('is_main_branch', 'desc')->orderBy('name');
                }
            ])->where('slug', $slug)->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data' => $dealer,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dealer not found.',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified dealer.
     */
    public function update(Request $request, string $slug)
    {
        try {
            $dealer = Dealer::where('slug', $slug)->firstOrFail();

            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'business_type' => 'nullable|string|max:100',
                'license_number' => 'nullable|string|max:100',
                'registration_number' => 'nullable|string|max:100',
                'phone' => 'required|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255|unique:dealers,email,' . $dealer->id,
                'owner_name' => 'nullable|string|max:255',
                'owner_phone' => 'nullable|string|max:20',
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
                'credit_limit' => 'nullable|numeric|min:0',
                'agreement_start_date' => 'nullable|date',
                'agreement_end_date' => 'nullable|date|after:agreement_start_date',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'logo' => 'nullable|string|max:255',
                'images' => 'nullable|array',
                'documents' => 'nullable|array',
                'status' => 'nullable|in:active,inactive,suspended,pending_approval',
                'is_verified' => 'nullable|boolean',
                'can_create_branches' => 'nullable|boolean',
                'notes' => 'nullable|string',
            ]);

            // Update slug if name changed
            if ($validatedData['name'] !== $dealer->name) {
                $slug = Str::slug($validatedData['name']);
                $originalSlug = $slug;
                $counter = 1;

                while (Dealer::where('slug', $slug)->where('id', '!=', $dealer->id)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
                $validatedData['slug'] = $slug;
            }

            $validatedData['updated_by'] = Auth::id();
            $dealer->update($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Dealer updated successfully.',
                'data' => $dealer->fresh(),
                'slug' => $dealer->slug,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update dealer.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified dealer.
     */
    public function destroy(string $slug)
    {
        try {
            $dealer = Dealer::where('slug', $slug)->firstOrFail();
            $dealer->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Dealer deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete dealer.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function dealersRaw(Request $request)
    {
        try {
            $searchQuery = $request->input('name');

            $query = Dealer::query()->where('status', 'active');

            if ($searchQuery) {
                $query->where('name', 'LIKE', "%{$searchQuery}%");
            }

            $dealers = $query->select('id', 'name', 'city')
                ->limit(50)
                ->get()
                ->map(function ($dealer) {
                    return [
                        'value' => $dealer->id,
                        'label' => $dealer->name
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $dealers,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dealers.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
