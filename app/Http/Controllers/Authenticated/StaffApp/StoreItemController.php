<?php

namespace App\Http\Controllers\Authenticated\StaffApp;

use App\Http\Controllers\Controller;
use App\Models\StoreItemInstance;
use App\Models\Part;
use App\Models\StoreItems;
use App\Models\PartRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StoreItemController extends Controller
{
    /**
     * Get all store item instances assigned to the currently authenticated staff member.
     */
    public function assignedItems(Request $request): JsonResponse
    {
        $staffUser = auth()->user();

        $query = StoreItemInstance::with([
            'storeItem:id,name,images,price,description',
            'assignedTo:id,first_name,last_name'
        ])
            ->where('assigned_to', $staffUser->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('barcode', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('item_instance_id', 'like', "%{$search}%")
                    ->orWhereHas('storeItem', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $items = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    /**
     * Return a store item to the store (unassign from staff)
     */
    public function returnItem(Request $request, $id): JsonResponse
    {
        $staffUser = auth()->user();

        $instance = StoreItemInstance::where('id', $id)
            ->where('assigned_to', $staffUser->id)
            ->first();

        if (!$instance) {
            return response()->json([
                'status' => 404,
                'message' => 'Item not found or not assigned to you',
            ], 404);
        }

        // Return the item: unassign it and set status back to active (available)
        $instance->update([
            'assigned_to' => null,
            'status' => 'active',
            'complaint_number' => null,
            'updated_by' => $staffUser->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Item successfully returned to the store',
            'data' => $instance,
        ]);
    }

    /**
     * Get list of parts available for request.
     */
    public function getParts(Request $request): JsonResponse
    {
        $parts = Part::where('status', 'active')
            ->select('id', 'name', 'part_number')
            ->get();

        $storeItems = StoreItems::where('status', 'active')
            ->select('id', 'name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'parts' => $parts,
                'store_items' => $storeItems,
            ],
        ]);
    }

    /**
     * Submit a request for a new part or store item.
     */
    public function submitPartRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:part,store_item',
            'item_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $user = auth()->user();

        $partRequest = new PartRequest([
            'quantity' => $validated['quantity'],
            'request_type' => 'payable', // Default logic
            'notes' => $validated['notes'],
            'status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        if ($validated['type'] === 'part') {
            $partRequest->part_id = $validated['item_id'];
        } else {
            $partRequest->store_item_id = $validated['item_id'];
        }

        $partRequest->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Request submitted successfully',
            'data' => $partRequest,
        ]);
    }
}
