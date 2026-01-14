<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\StoreItemInstance;
use App\Models\WorkOrderStoreItem;
use Illuminate\Http\Request;

class WorkOrderStoreItemController extends Controller
{
    /**
     * Get all store items linked to a work order.
     */
    public function index($workOrderId)
    {
        $items = WorkOrderStoreItem::where('work_order_id', $workOrderId)
            ->with([
                'storeItemInstance:id,item_instance_id,barcode,description,status',
                'storeItemInstance.storeItem:id,name,sku,price,images',
                'createdBy:id,first_name,last_name',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    /**
     * Link a store item instance to a work order.
     */
    public function store(Request $request, $workOrderId)
    {
        $validated = $request->validate([
            'store_item_instance_id' => 'required|exists:store_item_instances,id',
            'quantity_used' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();

        // Check if already linked
        $existing = WorkOrderStoreItem::where('work_order_id', $workOrderId)
            ->where('store_item_instance_id', $validated['store_item_instance_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'This store item is already linked to this work order',
            ], 422);
        }

        // Update the store item instance status to 'used'
        StoreItemInstance::find($validated['store_item_instance_id'])->update([
            'status' => 'used',
            'complaint_number' => $workOrderId,
        ]);

        $item = WorkOrderStoreItem::create([
            'work_order_id' => $workOrderId,
            'store_item_instance_id' => $validated['store_item_instance_id'],
            'quantity_used' => $validated['quantity_used'] ?? 1,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $item->load([
            'storeItemInstance:id,item_instance_id,barcode,description,status',
            'storeItemInstance.storeItem:id,name,sku,price,images',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Store item linked to work order successfully',
            'data' => $item,
        ]);
    }

    /**
     * Remove a store item from a work order.
     */
    public function destroy($workOrderId, $id)
    {
        $item = WorkOrderStoreItem::where('work_order_id', $workOrderId)
            ->findOrFail($id);

        // Update the store item instance status back to 'active'
        StoreItemInstance::find($item->store_item_instance_id)->update([
            'status' => 'active',
            'complaint_number' => null,
        ]);

        $item->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Store item removed from work order',
        ]);
    }

    /**
     * Search available store item instances (not used).
     */
    public function searchAvailable(Request $request)
    {
        $query = $request->input('q', '');

        $instances = StoreItemInstance::where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('item_instance_id', 'like', "%{$query}%")
                    ->orWhere('barcode', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->with('storeItem:id,name,sku,price,images')
            ->limit(20)
            ->get();

        // Format for SearchSelect component
        $formatted = $instances->map(function ($instance) {
            return [
                'id' => $instance->id,
                'name' => "{$instance->item_instance_id} - {$instance->storeItem->name}",
                'barcode' => $instance->barcode,
                'description' => $instance->description,
                'store_item' => $instance->storeItem,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ]);
    }
}
