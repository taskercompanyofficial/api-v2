<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\StoreItemInstance;
use App\Models\StoreItems;
use Illuminate\Http\Request;

class StoreItemInstanceController extends Controller
{
    /**
     * Display a listing of instances for a store item.
     */
    public function index(Request $request, $storeItemId)
    {
        $instances = StoreItemInstance::where('store_item_id', $storeItemId)
            ->with(['assignedTo:id,first_name,last_name', 'createdBy:id,first_name,last_name', 'storeItem:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $instances,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_item_id' => 'required|exists:store_items,id',
            'quantity' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $user = $request->user();
        $storeItem = StoreItems::find($validated['store_item_id']);

        $instances = [];

        for ($i = 0; $i < $validated['quantity']; $i++) {
            // Generate item_instance_id using store item name (first letter of each word) + sequential number
            $nameWords = explode(' ', $storeItem->name);
            $prefix = '';
            foreach ($nameWords as $word) {
                $prefix .= strtoupper(substr($word, 0, 1));
            }

            // Get the last instance number for this prefix
            $lastInstance = StoreItemInstance::where('item_instance_id', 'like', $prefix . '-%')->orderBy('id', 'desc')->first();
            $nextNumber = 1;

            if ($lastInstance) {
                $parts = explode('-', $lastInstance->item_instance_id);
                $nextNumber = intval(end($parts)) + 1;
            }

            $itemInstanceId = $prefix . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Generate barcode based on item_instance_id
            $barcode = 'BC' . str_replace('-', '', $itemInstanceId) . rand(1000, 9999);

            $instanceData = [
                'item_instance_id' => $itemInstanceId,
                'store_item_id' => $validated['store_item_id'],
                'barcode' => $barcode,
                'description' => $validated['description'] ?? $storeItem->description,
                'status' => 'active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ];

            $instance = StoreItemInstance::create($instanceData);
            $instances[] = $instance;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Store item instance(s) created successfully',
            'data' => $instances,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $instance = StoreItemInstance::with(['assignedTo:id,first_name,last_name', 'storeItem', 'createdBy:id,first_name,last_name', 'updatedBy:id,first_name,last_name'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $instance,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:active,inactive,reserved,used',
            'assigned_to' => 'nullable|exists:staff,id',
            'description' => 'nullable|string',
            'used_price' => 'nullable|numeric|min:0',
            'used_date' => 'nullable|date',
            'complaint_number' => 'nullable|string',
        ]);

        $user = $request->user();
        $instance = StoreItemInstance::findOrFail($id);

        $validated['updated_by'] = $user->id;
        $instance->update($validated);

        $instance->load(['assignedTo:id,first_name,last_name', 'storeItem:id,name']);

        return response()->json([
            'status' => 'success',
            'message' => 'Store item instance updated successfully',
            'data' => $instance,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $instance = StoreItemInstance::findOrFail($id);
        $instance->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Store item instance deleted successfully',
        ]);
    }

    /**
     * Bulk update status for multiple instances.
     */
    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'instance_ids' => 'required|array|min:1',
            'instance_ids.*' => 'required|exists:store_item_instances,id',
            'status' => 'required|string|in:active,inactive,reserved,used',
        ]);

        $user = $request->user();

        StoreItemInstance::whereIn('id', $validated['instance_ids'])->update([
            'status' => $validated['status'],
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => count($validated['instance_ids']) . ' instance(s) status updated to ' . $validated['status'],
        ]);
    }

    /**
     * Bulk assign multiple instances to a staff member.
     */
    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'instance_ids' => 'required|array|min:1',
            'instance_ids.*' => 'required|exists:store_item_instances,id',
            'assigned_to' => 'required|exists:staff,id',
        ]);

        $user = $request->user();

        StoreItemInstance::whereIn('id', $validated['instance_ids'])->update([
            'assigned_to' => $validated['assigned_to'],
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => count($validated['instance_ids']) . ' instance(s) assigned successfully',
        ]);
    }

    /**
     * Bulk delete multiple instances.
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'instance_ids' => 'required|array|min:1',
            'instance_ids.*' => 'required|exists:store_item_instances,id',
        ]);

        StoreItemInstance::whereIn('id', $validated['instance_ids'])->delete();

        return response()->json([
            'status' => 'success',
            'message' => count($validated['instance_ids']) . ' instance(s) deleted successfully',
        ]);
    }
}
