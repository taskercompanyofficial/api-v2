<?php

namespace App\Http\Controllers\TaskerApp;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Store a newly created order from the app
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'customer_address_id' => 'required|exists:customer_addresses,id',
            'payment_method' => 'required|in:cash,card,wallet',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.parent_service_id' => 'required|exists:parent_services,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:percentage,fixed',
            'items.*.scheduled_date' => 'required|date|after_or_equal:today',
            'items.*.scheduled_time' => 'required|date_format:H:i:s',
        ]);

        try {
            DB::beginTransaction();

            // Create order
            $order = Order::create([
                'customer_id' => $validated['customer_id'],
                'customer_address_id' => $validated['customer_address_id'],
                'order_number' => Order::generateOrderNumber(),
                'order_date' => now(),
                'notes' => $validated['notes'] ?? null,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pending',
                'status' => 'pending',
                'total_amount' => 0,
                'paid_amount' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ]);

            // Create order items
            foreach ($validated['items'] as $itemData) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'parent_service_id' => $itemData['parent_service_id'], // Keep for backward compatibility
                    'itemable_id' => $itemData['parent_service_id'], // Polymorphic reference
                    'itemable_type' => \App\Models\ParentServices::class, // Polymorphic type
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'discount' => $itemData['discount'] ?? 0,
                    'discount_type' => $itemData['discount_type'] ?? 'percentage',
                    'scheduled_date' => $itemData['scheduled_date'],
                    'scheduled_time' => $itemData['scheduled_time'],
                    'status' => 'scheduled',
                ]);
            }

            // Calculate total
            $order->calculateTotal();

            // Create status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'pending',
                'notes' => 'Order created',
            ]);

            DB::commit();

            // Create notification
            Notification::createNotification(
                $order->customer_id,
                'Order Confirmed',
                "Your order {$order->order_number} has been confirmed and will be processed soon.",
                'order',
                $order->id
            );

            // Load relationships for response
            $order->load(['items.parentService', 'customer', 'address']);

            return response()->json([
                'status' => 'success',
                'message' => 'Order created successfully',
                'data' => $order,
            ], 201);

        } catch (\Exception $err) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified order
     */
    public function show(string $orderNumber)
    {
        $order = Order::with([
            'items.parentService',
            'items.itemable', // Load polymorphic relationship
            'items.technician:id,name',
            'customer:id,name,phone,email',
            'address',
            'statusHistory.changedBy:id,name'
        ])
        ->where('order_number', $orderNumber)
        ->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $order,
        ]);
    }

    /**
     * Get customer's orders
     */
    public function customerOrders(Request $request)
    {
        $customerId = $request->user()->id;

        $orders = Order::with(['items.parentService', 'items.itemable'])
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $orders,
        ]);
    }

    /**
     * Cancel an order
     */
    public function cancel(Request $request, string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found',
            ], 404);
        }

        // Check if order can be cancelled
        if (in_array($order->status, ['completed', 'cancelled', 'refunded'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order cannot be cancelled',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $oldStatus = $order->status;
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->save();

            // Cancel all items
            $order->items()->update(['status' => 'cancelled']);

            // Create status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $oldStatus,
                'to_status' => 'cancelled',
                'notes' => $request->input('reason', 'Cancelled by customer'),
            ]);

            DB::commit();

            // Create notification
            Notification::createNotification(
                $order->customer_id,
                'Order Cancelled',
                "Your order {$order->order_number} has been cancelled.",
                'order',
                $order->id
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Order cancelled successfully',
            ]);

        } catch (\Exception $err) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }
}
