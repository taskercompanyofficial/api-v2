<?php

namespace App\Http\Controllers\TaskerApp;

use App\Http\Controllers\Controller;
use App\Models\FreeInstallation;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FreeInstallationController extends Controller
{
    public function store(Request $request)
    {
        $customer = $request->user();

        $validated = $request->validate([
            'contact_person' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'product_type' => 'required|in:washing_machine,air_conditioner',
            'brand_id' => 'required|exists:authorized_brands,id',
            'product_model' => 'required|string|max:255',
            'purchase_date' => 'required|date',
            'invoice_image' => 'required|image|mimes:jpeg,jpg,png|max:2048',
            'warranty_image' => 'required|image|mimes:jpeg,jpg,png|max:2048',
            'is_delivered' => 'boolean',
            'is_wiring_done' => 'boolean',
            'outdoor_bracket_needed' => 'boolean',
            'extra_pipes' => 'integer|min:0',
            'extra_holes' => 'integer|min:0',
            'city' => 'required|string|max:255',
            'address_id' => 'nullable|exists:customer_addresses,id',
        ]);

        try {
            DB::beginTransaction();

            // Handle invoice image upload
            if ($request->hasFile('invoice_image')) {
                $file = $request->file('invoice_image');
                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('free-installation-invoices', $fileName, 'public');
                $validated['invoice_image'] = $filePath;
            }

            // Handle warranty image upload
            if ($request->hasFile('warranty_image')) {
                $file = $request->file('warranty_image');
                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('free-installation-warranties', $fileName, 'public');
                $validated['warranty_image'] = $filePath;
            }

            // Create order first
            $order = Order::create([
                'customer_id' => $customer->id,
                'customer_address_id' => $validated['address_id'],
                'order_number' => Order::generateOrderNumber(),
                'order_date' => now(),
                'notes' => 'Free Installation Service - ' . ucfirst(str_replace('_', ' ', $validated['product_type'])),
                'payment_method' => 'cash',
                'payment_status' => 'pending',
                'status' => 'pending',
                'total_amount' => 0,
                'paid_amount' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ]);

            // Create free installation record first
            $validated['customer_id'] = $customer->id;
            $validated['status'] = 'pending';
            $validated['order_id'] = $order->id;

            $installation = FreeInstallation::create($validated);

            // Create order item using polymorphic relationship
            OrderItem::create([
                'order_id' => $order->id,
                'itemable_id' => $installation->id,
                'itemable_type' => FreeInstallation::class,
                'quantity' => 1,
                'unit_price' => 0, // Free service
                'discount' => 0,
                'discount_type' => 'fixed',
                'subtotal' => 0,
                'scheduled_date' => now()->addDays(2), // Default 2 days from now
                'scheduled_time' => '10:00:00',
                'status' => 'pending',
            ]);
            
            // Create status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'pending',
                'notes' => 'Free installation order created',
            ]);

            // Create notification for customer
            Notification::createNotification(
                $customer->id,
                'App\Models\Customer',
                'Free Installation Request Received',
                "Your free installation request for {$installation->product_model} has been received. Order number: {$order->order_number}. Our team will contact you within 24-48 hours.",
                'free_installation',
                [
                    'installation_id' => $installation->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Free installation request submitted successfully. Our team will contact you within 24-48 hours.',
                'data' => [
                    'installation' => $installation,
                    'order' => $order->load('items.itemable'),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit free installation request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $customer = $request->user();

        $installations = FreeInstallation::where('customer_id', $customer->id)
            ->with(['brand', 'order'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $installations,
        ]);
    }

    public function show(Request $request, $id)
    {
        $customer = $request->user();

        $installation = FreeInstallation::where('customer_id', $customer->id)
            ->where('id', $id)
            ->with(['brand', 'order'])
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $installation,
        ]);
    }
}
