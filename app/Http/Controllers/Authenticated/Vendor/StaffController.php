<?php

namespace App\Http\Controllers\Authenticated\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorStaff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user();
        $staff = VendorStaff::where('vendor_id', $vendor->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $staff,
        ]);
    }

    public function store(Request $request)
    {
        $vendor = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'cnic_front' => 'required|image|max:2048',
            'cnic_back' => 'required|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $staff = new VendorStaff();
            $staff->vendor_id = $vendor->id;
            $staff->name = $request->name;
            $staff->phone = $request->phone;
            $staff->status = 'pending';

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
                'message' => 'Staff member added successfully and is pending approval.',
                'data' => $staff,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
