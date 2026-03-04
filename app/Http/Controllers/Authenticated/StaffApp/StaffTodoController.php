<?php

namespace App\Http\Controllers\Authenticated\StaffApp;

use App\Http\Controllers\Controller;
use App\Models\StaffTodo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class StaffTodoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $staff = $request->user();
            $todos = StaffTodo::where('staff_id', $staff->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $todos,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $staff = $request->user();
            $todo = StaffTodo::create([
                'staff_id' => $staff->id,
                'title' => $request->title,
                'description' => $request->description,
                'due_date' => $request->due_date,
                'priority' => $request->priority ?? 'medium',
                'status' => 'pending',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Todo created successfully',
                'data' => $todo,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,completed',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $staff = $request->user();
            $todo = StaffTodo::where('staff_id', $staff->id)->findOrFail($id);
            $todo->update($request->only(['status', 'title', 'description', 'due_date', 'priority']));

            return response()->json([
                'status' => 'success',
                'message' => 'Todo updated successfully',
                'data' => $todo,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        try {
            $staff = $request->user();
            $todo = StaffTodo::where('staff_id', $staff->id)->findOrFail($id);
            $todo->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Todo deleted successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
