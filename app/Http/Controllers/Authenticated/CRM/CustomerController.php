<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\QueryFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    use QueryFilterTrait;
    public function index(Request $request)
    {
        try {
            $page = $request->input('page') ?? 1;
            $perPage = $request->input('perPage') ?? 50;

            $query = Customer::query()->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'address'
            ]);
            if ($request->has('name') && $request->name) {
                $search = $request->input('name');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('whatsapp', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            }
            $this->applyJsonFilters($query, $request);
            $this->applySorting($query, $request);

            $customer =  $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'data' => $customer->items(),
                'pagination' => [
                    'total' => $customer->total(),
                    'per_page' => $customer->perPage(),
                    'current_page' => $customer->currentPage(),
                    'last_page' => $customer->lastPage(),
                    'from' => $customer->firstItem(),
                    'to' => $customer->lastItem(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customers.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'avatar' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255|unique:customers,email',
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'customer_id' => 'nullable|string|max:255|unique:customers,customer_id',
                'is_care_of_customer' => 'nullable|boolean',
                'status' => 'nullable|string|max:50', // e.g., 'active', 'inactive'
                'description' => 'nullable|string',
                'kind_of_issue' => 'nullable|string|max:255',
            ]);
            $customerId = 'cus' . strtoupper(bin2hex(random_bytes(4)));
            $validatedData['customer_id'] = $customerId;
            $user = $request->user();
            $validatedData['created_by'] = $user->id;
            $validatedData['updated_by'] = $user->id;
            $customer = Customer::create($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Customer created successfully.',
                'data' => $customer,
            ]);
        } catch (Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $customer = Customer::with([
                'createdBy:id,name',
                'updatedBy:id,name',
            ])->find($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found.',
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => $customer,
            ]);
        } catch (Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found.',
                ]);
            }

            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'avatar' => 'nullable|string|max:255',
                'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)],
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'customer_id' => ['nullable', 'string', 'max:255', Rule::unique('customers', 'customer_id')->ignore($customer->id)],
                'is_care_of_customer' => 'nullable|boolean',
                'status' => 'nullable|string|max:50',
                'description' => 'nullable|string',
                'kind_of_issue' => 'nullable|string|max:255',
            ]);

            $user = $request->user();
            $validatedData['created_by'] = $user->id;
            $validatedData['updated_by'] = $user->id;
            $customer->update($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Customer updated successfully.',
                'data' => $customer,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update customer.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found.',
                ]);
            }

            $customer->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Customer deleted successfully.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete customer.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function customersRaw(Request $request)
    {
        try {
            // Get search query parameter
            $searchQuery = $request->input('name');

            $query = Customer::query();
            // If search query exists, search across multiple fields
            if ($searchQuery) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('name', 'LIKE', "%{$searchQuery}%")
                        ->orWhere('phone', 'LIKE', "%{$searchQuery}%")
                        ->orWhere('whatsapp', 'LIKE', "%{$searchQuery}%")
                        ->orWhere('email', 'LIKE', "%{$searchQuery}%");
                });
            }

            // Get customers and format for SearchSelect component
            $customers = $query->select('id', 'name', 'email', 'phone', 'whatsapp', 'avatar')
                ->limit(50) // Limit results for performance
                ->get()
                ->map(function ($customer) {
                    return [
                        'value' => $customer->id,
                        'label' => $customer->name,
                        'description' => $customer->phone,
                        'image' => $customer->avatar_url,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $customers,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customers.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
