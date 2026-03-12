<?php

namespace App\Http\Controllers\Authenticated\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorStaff;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    /**
     * List all staff for the authenticated vendor
     */
    public function index(Request $request)
    {
        $vendor = $request->user();

        if (!$vendor instanceof \App\Models\Vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only vendors can access staff records.',
            ], 403);
        }

        $staff = VendorStaff::where('vendor_id', $vendor->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($member) {
                // Attach work order counts for each staff member
                $member->assigned_work_orders_count = WorkOrder::where('vendor_staff_id', $member->id)->count();
                $member->active_work_orders_count = WorkOrder::where('vendor_staff_id', $member->id)
                    ->whereNotNull('accepted_at')
                    ->whereNull('completed_at')
                    ->whereNull('cancelled_at')
                    ->count();
                $member->completed_work_orders_count = WorkOrder::where('vendor_staff_id', $member->id)
                    ->whereNotNull('completed_at')
                    ->count();
                return $member;
            });

        return response()->json([
            'status' => 'success',
            'data' => $staff,
        ]);
    }

    /**
     * Show a single staff member with full stats
     */
    public function show(Request $request, $id)
    {
        $vendor = $request->user();

        if (!$vendor instanceof \App\Models\Vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $staff = VendorStaff::where('vendor_id', $vendor->id)->findOrFail($id);

        // Get work order stats
        $baseQuery = WorkOrder::where('vendor_staff_id', $staff->id);
        $stats = [
            'total_assigned' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)
                ->whereNotNull('accepted_at')
                ->whereNull('completed_at')
                ->whereNull('cancelled_at')
                ->count(),
            'completed' => (clone $baseQuery)->whereNotNull('completed_at')->count(),
            'completed_this_month' => (clone $baseQuery)
                ->whereNotNull('completed_at')
                ->whereMonth('completed_at', now()->month)
                ->whereYear('completed_at', now()->year)
                ->count(),
            'pending' => (clone $baseQuery)
                ->whereNull('accepted_at')
                ->whereNull('rejected_at')
                ->count(),
        ];

        $staff->stats = $stats;

        return response()->json([
            'status' => 'success',
            'data' => $staff,
        ]);
    }

    /**
     * Create a new staff member
     */
    public function store(Request $request)
    {
        $vendor = $request->user();

        if (!$vendor instanceof \App\Models\Vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only vendors can add staff members.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'cnic' => 'nullable|string|max:20',
            'experience' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
            'cnic_front' => 'nullable|image|max:2048',
            'cnic_back' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if phone already exists for this vendor
        $existing = VendorStaff::where('vendor_id', $vendor->id)
            ->where('phone', $request->phone)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'A staff member with this phone number already exists.',
            ], 422);
        }

        try {
            $staff = new VendorStaff();
            $staff->vendor_id = $vendor->id;
            $staff->name = $request->name;
            $staff->phone = $request->phone;
            $staff->cnic = $request->cnic;
            $staff->experience = $request->experience;
            $staff->status = 'approved'; // Auto-approve vendor-created staff

            // Auto-generate password: last 6 digits of phone
            $rawPassword = substr(preg_replace('/\D/', '', $request->phone), -6);
            $staff->password = Hash::make($rawPassword);

            // Profile image
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = 'profile_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $staff->image = $file->storeAs('vendor-staff/' . $vendor->id, $filename, 'public');
            }

            if ($request->hasFile('cnic_front')) {
                $file = $request->file('cnic_front');
                $filename = 'cnic_front_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $staff->cnic_front_image = $file->storeAs('vendor-staff/' . $vendor->id, $filename, 'public');
            }

            if ($request->hasFile('cnic_back')) {
                $file = $request->file('cnic_back');
                $filename = 'cnic_back_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $staff->cnic_back_image = $file->storeAs('vendor-staff/' . $vendor->id, $filename, 'public');
            }

            $staff->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Staff member added successfully. Default password: ' . $rawPassword,
                'data' => $staff,
                'default_password' => $rawPassword,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a staff member
     */
    public function update(Request $request, $id)
    {
        $vendor = $request->user();

        if (!$vendor instanceof \App\Models\Vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $staff = VendorStaff::where('vendor_id', $vendor->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'cnic' => 'nullable|string|max:20',
            'experience' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
            'status' => 'sometimes|in:approved,pending,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->has('name')) $staff->name = $request->name;
            if ($request->has('phone')) $staff->phone = $request->phone;
            if ($request->has('cnic')) $staff->cnic = $request->cnic;
            if ($request->has('experience')) $staff->experience = $request->experience;
            if ($request->has('status')) $staff->status = $request->status;

            // Handle Profile Image Update
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = 'profile_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $staff->image = $file->storeAs('vendor-staff/' . $vendor->id, $filename, 'public');
            }

            $staff->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Staff member updated successfully.',
                'data' => $staff,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a staff member
     */
    public function destroy(Request $request, $id)
    {
        $vendor = $request->user();

        if (!$vendor instanceof \App\Models\Vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $staff = VendorStaff::where('vendor_id', $vendor->id)->findOrFail($id);

        // Unassign from any work orders
        WorkOrder::where('vendor_staff_id', $staff->id)->update(['vendor_staff_id' => null]);

        $staff->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Staff member removed successfully.',
        ]);
    }

    /**
     * Toggle staff status (approve/suspend)
     */
    public function toggleStatus(Request $request, $id)
    {
        $vendor = $request->user();

        if (!$vendor instanceof \App\Models\Vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $staff = VendorStaff::where('vendor_id', $vendor->id)->findOrFail($id);

        $staff->status = $staff->status === 'approved' ? 'pending' : 'approved';
        $staff->save();

        return response()->json([
            'status' => 'success',
            'message' => $staff->status === 'approved' ? 'Staff member activated.' : 'Staff member suspended.',
            'data' => $staff,
        ]);
    }

    /**
     * Get work orders assigned to a specific staff member
     */
    public function staffWorkOrders(Request $request, $id)
    {
        $vendor = $request->user();

        if (!$vendor instanceof \App\Models\Vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $staff = VendorStaff::where('vendor_id', $vendor->id)->findOrFail($id);

        $query = WorkOrder::with([
            'customer:id,name,phone',
            'address:id,address_line_1,city,state',
            'status:id,name,slug',
            'subStatus:id,name,slug',
            'service:id,name',
            'parentService:id,name',
        ])->where('vendor_staff_id', $staff->id);

        // Filter by status
        if ($request->has('status')) {
            $statusFilter = $request->status;
            if ($statusFilter === 'active') {
                $query->whereNotNull('accepted_at')
                    ->whereNull('completed_at')
                    ->whereNull('cancelled_at');
            } elseif ($statusFilter === 'completed') {
                $query->whereNotNull('completed_at');
            }
        }

        $workOrders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $workOrders,
        ]);
    }

    /**
     * Reset staff password to default
     */
    public function resetPassword(Request $request, $id)
    {
        $vendor = $request->user();

        if (!$vendor instanceof \App\Models\Vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $staff = VendorStaff::where('vendor_id', $vendor->id)->findOrFail($id);

        $rawPassword = substr(preg_replace('/\D/', '', $staff->phone), -6);
        $staff->password = Hash::make($rawPassword);
        $staff->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset to default: ' . $rawPassword,
            'default_password' => $rawPassword,
        ]);
    }
}
