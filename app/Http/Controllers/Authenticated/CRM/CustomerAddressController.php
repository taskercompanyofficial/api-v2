<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use App\Models\CustomerAddress;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class CustomerAddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $addresses = CustomerAddress::where('customer_id', $request->customer_id)->get();
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
            'customer_id' => 'required|exists:customers,id',
                'address_type' => 'required|string|max:255',
                'area_type' => 'required|string|max:255',
                'address_line_1' => 'required|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'city' => 'required|string|max:255',
                'state' => 'required|string|max:255',
                'zip_code' => 'required|string|max:10',
                'country' => 'required|string|max:255',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'status' => 'required|string|max:255',
            ]);
            try {

            $validatedData['created_by'] = Auth::id();
            $validatedData['updated_by'] = Auth::id();

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
}
