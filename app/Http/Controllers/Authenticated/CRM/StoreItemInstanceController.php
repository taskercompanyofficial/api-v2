<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\StoreItemInstance;
use Illuminate\Http\Request;

class StoreItemInstanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        $storeItem = \App\Models\StoreItems::find($validated['store_item_id']);
        
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
                'status' => $validated['status'] ?? 'active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ];
            
            $instance = StoreItemInstance::create($instanceData);
            $instances[] = $instance;
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Store item instance(s) created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
