<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\BusinessPhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessPhoneNumberController extends Controller
{
    /**
     * Get all business phone numbers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BusinessPhoneNumber::with('apiToken');

        // Filter by platform
        if ($request->has('platform')) {
            $query->ofPlatform($request->platform);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter verified only
        if ($request->boolean('verified_only')) {
            $query->verified();
        }

        // Filter active only
        if ($request->boolean('active_only')) {
            $query->active();
        }

        $phoneNumbers = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($phoneNumbers);
    }

    /**
     * Get a specific business phone number.
     */
    public function show(int $id): JsonResponse
    {
        $phoneNumber = BusinessPhoneNumber::with('apiToken')->findOrFail($id);

        return response()->json($phoneNumber);
    }

    /**
     * Create a new business phone number.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'api_token_id' => 'required|exists:api_tokens,id',
            'phone_number' => 'required|string|unique:business_phone_numbers,phone_number',
            'phone_number_id' => 'required|string|unique:business_phone_numbers,phone_number_id',
            'business_account_id' => 'required|string',
            'display_name' => 'nullable|string|max:255',
            'platform' => 'required|in:whatsapp,telegram,viber,other',
            'capabilities' => 'nullable|array',
            'metadata' => 'nullable|array',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $phoneNumber = BusinessPhoneNumber::create([
            'api_token_id' => $request->api_token_id,
            'phone_number' => $request->phone_number,
            'phone_number_id' => $request->phone_number_id,
            'business_account_id' => $request->business_account_id,
            'display_name' => $request->display_name,
            'platform' => $request->platform,
            'status' => 'pending_verification',
            'capabilities' => $request->capabilities,
            'metadata' => $request->metadata,
            'is_default' => $request->get('is_default', false),
        ]);

        // Set as default if requested
        if ($request->boolean('is_default')) {
            $phoneNumber->setAsDefault();
        }

        return response()->json([
            'success' => true,
            'message' => 'Business phone number created successfully',
            'data' => $phoneNumber->fresh('apiToken'),
        ], 201);
    }

    /**
     * Update a business phone number.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'display_name' => 'sometimes|string|max:255',
            'capabilities' => 'sometimes|array',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $phoneNumber = BusinessPhoneNumber::findOrFail($id);
        $phoneNumber->update($request->only([
            'display_name',
            'capabilities',
            'metadata',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Business phone number updated successfully',
            'data' => $phoneNumber->fresh('apiToken'),
        ]);
    }

    /**
     * Delete a business phone number.
     */
    public function destroy(int $id): JsonResponse
    {
        $phoneNumber = BusinessPhoneNumber::findOrFail($id);
        $phoneNumber->delete();

        return response()->json([
            'success' => true,
            'message' => 'Business phone number deleted successfully',
        ]);
    }

    /**
     * Verify a business phone number.
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $phoneNumber = BusinessPhoneNumber::findOrFail($id);
        $phoneNumber->markAsVerified();

        return response()->json([
            'success' => true,
            'message' => 'Business phone number verified successfully',
            'data' => $phoneNumber->fresh('apiToken'),
        ]);
    }

    /**
     * Set as default phone number.
     */
    public function setDefault(int $id): JsonResponse
    {
        $phoneNumber = BusinessPhoneNumber::findOrFail($id);
        
        if (!$phoneNumber->isVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot set unverified phone number as default',
            ], 400);
        }

        $phoneNumber->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => 'Phone number set as default',
            'data' => $phoneNumber->fresh('apiToken'),
        ]);
    }

    /**
     * Activate a phone number.
     */
    public function activate(int $id): JsonResponse
    {
        $phoneNumber = BusinessPhoneNumber::findOrFail($id);
        $phoneNumber->activate();

        return response()->json([
            'success' => true,
            'message' => 'Phone number activated',
            'data' => $phoneNumber->fresh('apiToken'),
        ]);
    }

    /**
     * Deactivate a phone number.
     */
    public function deactivate(int $id): JsonResponse
    {
        $phoneNumber = BusinessPhoneNumber::findOrFail($id);
        $phoneNumber->deactivate();

        return response()->json([
            'success' => true,
            'message' => 'Phone number deactivated',
            'data' => $phoneNumber->fresh('apiToken'),
        ]);
    }

    /**
     * Suspend a phone number.
     */
    public function suspend(int $id): JsonResponse
    {
        $phoneNumber = BusinessPhoneNumber::findOrFail($id);
        $phoneNumber->suspend();

        return response()->json([
            'success' => true,
            'message' => 'Phone number suspended',
            'data' => $phoneNumber->fresh('apiToken'),
        ]);
    }
}
