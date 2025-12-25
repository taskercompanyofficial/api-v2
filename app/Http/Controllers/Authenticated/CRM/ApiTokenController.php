<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiTokenController extends Controller
{
    /**
     * Get all API tokens.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApiToken::query();

        // Filter by type
        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            if ($request->boolean('is_active')) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        // Filter non-expired
        if ($request->boolean('not_expired')) {
            $query->notExpired();
        }

        $tokens = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($tokens);
    }

    /**
     * Get a specific API token.
     */
    public function show(int $id): JsonResponse
    {
        $token = ApiToken::with('businessPhoneNumbers')->findOrFail($id);

        return response()->json($token);
    }

    /**
     * Create a new API token.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:whatsapp,facebook,instagram,google,stripe,other',
            'token' => 'required|string',
            'token_type' => 'nullable|string',
            'refresh_token' => 'nullable|string',
            'expires_at' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $token = ApiToken::create([
            'name' => $request->name,
            'type' => $request->type,
            'token' => $request->token, // Will be encrypted automatically
            'token_type' => $request->get('token_type', 'Bearer'),
            'refresh_token' => $request->refresh_token,
            'expires_at' => $request->expires_at,
            'metadata' => $request->metadata,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API token created successfully',
            'data' => $token,
        ], 201);
    }

    /**
     * Update an API token.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'token' => 'sometimes|string',
            'token_type' => 'sometimes|string',
            'refresh_token' => 'nullable|string',
            'expires_at' => 'nullable|date',
            'metadata' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $token = ApiToken::findOrFail($id);
        $token->update($request->only([
            'name',
            'token',
            'token_type',
            'refresh_token',
            'expires_at',
            'metadata',
            'is_active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'API token updated successfully',
            'data' => $token->fresh(),
        ]);
    }

    /**
     * Delete an API token.
     */
    public function destroy(int $id): JsonResponse
    {
        $token = ApiToken::findOrFail($id);
        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'API token deleted successfully',
        ]);
    }

    /**
     * Activate an API token.
     */
    public function activate(int $id): JsonResponse
    {
        $token = ApiToken::findOrFail($id);
        $token->activate();

        return response()->json([
            'success' => true,
            'message' => 'API token activated',
            'data' => $token->fresh(),
        ]);
    }

    /**
     * Deactivate an API token.
     */
    public function deactivate(int $id): JsonResponse
    {
        $token = ApiToken::findOrFail($id);
        $token->deactivate();

        return response()->json([
            'success' => true,
            'message' => 'API token deactivated',
            'data' => $token->fresh(),
        ]);
    }
}
