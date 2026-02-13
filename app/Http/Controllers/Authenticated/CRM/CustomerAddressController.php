<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\Auth;

class CustomerAddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $addresses = CustomerAddress::where('customer_id', $request->customer_id)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();
        return response()->json([
'status' => 'success',
            'data' => $addresses
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validatedData = $request->validate([
            'customer_id' => 'sometimes|required|exists:customers,id',
                'address_type' => 'sometimes|required|string|max:255',
                'area_type' => 'sometimes|required|string|max:255',
                'address_line_1' => 'sometimes|required|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'city' => 'sometimes|required|string|max:255',
                'state' => 'nullable|string|max:255',
                'zip_code' => 'nullable|string|max:10',
                'country' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'area' => 'nullable|string|max:255'
            ]);
            try {

            $validatedData['created_by'] = Auth::id();
            $validatedData['updated_by'] = Auth::id();

            // If customer has no addresses yet, set first as default
            $hasAny = CustomerAddress::where('customer_id', $validatedData['customer_id'] ?? null)->exists();
            $validatedData['is_default'] = $validatedData['is_default'] ?? !$hasAny;

            $address = CustomerAddress::create($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Customer address created successfully',
            ], );
        } catch (Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): \Illuminate\Http\JsonResponse
    {
        $address = CustomerAddress::find($id);

        if (!$address) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer address not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $address
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $address = CustomerAddress::find($id);

        if (!$address) {
            return response()->json(['message' => 'Customer address not found'], 404);
        }

        try {
            $validatedData = $request->validate([
                'customer_id' => 'sometimes|required|exists:customers,id',
                'address_type' => 'sometimes|required|string|max:255',
                'area_type' => 'sometimes|required|string|max:255',
                'address_line_1' => 'sometimes|required|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'city' => 'sometimes|required|string|max:255',
                'state' => 'sometimes|required|string|max:255',
                'zip_code' => 'sometimes|required|string|max:10',
                'country' => 'sometimes|required|string|max:255',
                'latitude' => 'sometimes|nullable|numeric',
                'longitude' => 'sometimes|nullable|numeric',
                'status' => 'sometimes|required|string|max:255',
                'is_default' => 'sometimes|boolean',
            ]);

            $validatedData['updated_by'] = Auth::id();

            $address->update($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Customer address updated successfully',
            ], );
        } catch (Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], );
        }
    }

    /**
     * Mark the given address as default for the customer.
     */
    public function setDefault(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $address = CustomerAddress::find($id);
        if (!$address) {
            return response()->json(['status' => 'error', 'message' => 'Customer address not found'], 404);
        }
        $customerId = $request->input('customer_id', $address->customer_id);
        if ((int)$customerId !== (int)$address->customer_id) {
            return response()->json(['status' => 'error', 'message' => 'Customer mismatch for address'], 422);
        }
        try {
            // Unset previous defaults safely within customer scope
            CustomerAddress::where('customer_id', $address->customer_id)->update(['is_default' => false]);
            $address->is_default = true;
            $address->updated_by = Auth::id();
            $address->save();
            return response()->json([
                'status' => 'success',
                'message' => 'Default address updated',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update default address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): \Illuminate\Http\JsonResponse
    {
        $address = CustomerAddress::find($id);

        if (!$address) {
            return response()->json(['message' => 'Customer address not found'], 404);
        }

        $address->delete();

        return response()->json(['message' => 'Customer address deleted successfully'], 204); // 204 No Content
    }

    public function addressesRaw(Request $request)
    {
        try {
            $customerId = $request->input('customer_id');

            if (!$customerId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'customer_id is required',
                    'data' => null,
                ]);
            }

            $addresses = CustomerAddress::where('customer_id', $customerId)
                ->where('status', 'active')
                ->select('id', 'address_type', 'area_type', 'address_line_1', 'address_line_2', 'city', 'state', 'zip_code', 'country')
                ->get()
                ->map(function ($address) {
                    return [
                        'value' => $address->id,
                        'label' => "{$address->address_type} - {$address->city}",
                        'description' => $address->address_line_1,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $addresses,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve addresses.',
                'error' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
