<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->input('user_id');
        
        if ($userId) {
            $user = User::with('status')->findOrFail($userId);
            return response()->json([
                'status' => 'success',
                'data' => $user->status
            ]);
        }
        
        $users = User::with('status')->get();
        return response()->json([
            'status' => 'success',
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status
                ];
            })
        ]);
    }

    /**
     * Assign a status to a user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'status_id' => 'required|exists:statuses,id',
        ]);
        
        $user = User::findOrFail($validated['user_id']);
        $user->status_id = $validated['status_id'];
        $user->updated_by = Auth::id();
        $user->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Status assigned successfully',
            'data' => $user->load('status')
        ]);
    }

    /**
     * Update the status for a specific user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'status_id' => 'required|exists:statuses,id',
        ]);
        
        $user->status_id = $validated['status_id'];
        $user->updated_by = Auth::id();
        $user->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Status updated successfully',
            'data' => $user->load('status')
        ]);
    }

    /**
     * Remove the status from a user.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->status_id = null;
        $user->updated_by = Auth::id();
        $user->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Status removed successfully'
        ]);
    }
}
