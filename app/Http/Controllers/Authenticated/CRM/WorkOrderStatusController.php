<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\WorkOrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WorkOrderStatusController extends Controller
{
    /**
     * Display a listing of work order statuses.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WorkOrderStatus::with(['children', 'parent']);

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Filter by parent statuses only
        if ($request->has('parents_only') && $request->boolean('parents_only')) {
            $query->parents();
        }

        // Filter by children statuses only
        if ($request->has('children_only') && $request->boolean('children_only')) {
            $query->children();
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Order by custom order field, then by name
        $statuses = $query->orderBy('order')->orderBy('name')->get();

        // Build hierarchical structure if requested
        if ($request->has('tree') && $request->boolean('tree')) {
            $tree = $this->buildTree($statuses);
            return response()->json([
                'success' => true,
                'data' => $tree,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $statuses,
        ]);
    }

    /**
     * Store a newly created work order status.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|exists:work_order_statuses,id',
            'name' => 'required|string|max:255|unique:work_order_statuses,name',
            'description' => 'nullable|string',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ]);
        }

        $status = WorkOrderStatus::create([
            ...$request->only([
                'parent_id',
                'name',
                'description',
                'color',
                'icon',
                'order',
                'is_active',
            ]),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $status->load(['parent', 'children']);

        return response()->json([
            'status' => 'success',
            'message' => 'Work order status created successfully.',
        ]);
    }

    /**
     * Display the specified work order status.
     */
    public function show(string $id): JsonResponse
    {
        $status = WorkOrderStatus::with(['parent', 'children', 'createdBy', 'updatedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Update the specified work order status.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $status = WorkOrderStatus::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'parent_id' => [
                'nullable',
                'exists:work_order_statuses,id',
                function ($attribute, $value, $fail) use ($id) {
                    // Prevent setting parent to itself or its own descendant
                    if ($value == $id) {
                        $fail('A status cannot be its own parent.');
                    }
                },
            ],
            'name' => 'sometimes|required|string|max:255|unique:work_order_statuses,name,' . $id,
            'description' => 'nullable|string',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $status->update([
            ...$request->only([
                'parent_id',
                'name',
                'description',
                'color',
                'icon',
                'order',
                'is_active',
            ]),
            'updated_by' => Auth::id(),
        ]);

        $status->load(['parent', 'children']);

        return response()->json([
            'success' => true,
            'message' => 'Work order status updated successfully.',
            'data' => $status,
        ]);
    }

    /**
     * Remove the specified work order status.
     */
    public function destroy(string $id): JsonResponse
    {
        $status = WorkOrderStatus::findOrFail($id);

        // Check if status has work orders
        $workOrderCount = $status->workOrders()->count();
        if ($workOrderCount > 0) {
            return response()->json([
                'status' => "error",
                'message' => "Cannot delete status. It is currently assigned to {$workOrderCount} work order(s).",
            ]);
        }

        // Handle children - either reassign or prevent deletion
        if ($status->children()->count() > 0) {
            return response()->json([
                'status' => "error",
                'message' => 'Cannot delete status with child statuses. Please delete or reassign child statuses first.',
            ], );
        }

        $status->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Work order status deleted successfully.',
        ]);
    }

    /**
     * Build a hierarchical tree structure from flat list
     */
    private function buildTree($statuses, $parentId = null)
    {
        $tree = [];

        foreach ($statuses as $status) {
            if ($status->parent_id == $parentId) {
                $children = $this->buildTree($statuses, $status->id);
                
                $node = $status->toArray();
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                
                $tree[] = $node;
            }
        }

        return $tree;
    }
}
