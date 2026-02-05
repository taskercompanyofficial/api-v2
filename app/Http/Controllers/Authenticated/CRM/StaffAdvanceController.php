<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\StaffAdvance;
use App\QueryFilterTrait;
use Illuminate\Http\Request;

class StaffAdvanceController extends Controller
{
    use QueryFilterTrait;

    /**
     * Get all staff advances
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('perPage', 10);
        $staffId = $request->input('staff_id');

        $query = StaffAdvance::query()
            ->with(['staff:id,code,first_name,middle_name,last_name', 'creator:id,first_name,last_name', 'approver:id,first_name,last_name', 'deductions'])
            ->orderBy('created_at', 'desc');

        // Filter by staff if provided
        if ($staffId) {
            $query->where('staff_id', $staffId);
        }

        // Apply filters from QueryFilterTrait
        $this->applyJsonFilters($query, $request);

        // Apply sorting
        if ($request->has('sort')) {
            $this->applySorting($query, $request);
        }

        $advances = $query->paginate($perPage, ['*'], 'page', $page);

        // Add formatted staff name and calculate progress
        $data = $advances->map(function ($advance) {
            $staff = $advance->staff;
            if ($staff) {
                $advance->staff->name = trim($staff->first_name . ' ' . ($staff->middle_name ?? '') . ' ' . $staff->last_name);
            }
            if ($advance->creator) {
                $advance->creator->name = trim($advance->creator->first_name . ' ' . $advance->creator->last_name);
            }
            if ($advance->approver) {
                $advance->approver->name = trim($advance->approver->first_name . ' ' . $advance->approver->last_name);
            }

            // Calculate payment progress
            $advance->payment_percentage = $advance->amount > 0
                ? round(($advance->amount_paid / $advance->amount) * 100, 2)
                : 0;

            return $advance;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'current_page' => $advances->currentPage(),
                'last_page' => $advances->lastPage(),
                'per_page' => $advances->perPage(),
                'total' => $advances->total(),
            ],
        ]);
    }

    /**
     * Get a single advance
     */
    public function show($id)
    {
        $advance = StaffAdvance::with([
            'staff:id,code,first_name,middle_name,last_name',
            'creator:id,first_name,last_name',
            'approver:id,first_name,last_name',
            'deductions.salaryPayout:id,month,final_payable'
        ])
            ->findOrFail($id);

        // Format names
        if ($advance->staff) {
            $advance->staff->name = trim($advance->staff->first_name . ' ' . ($advance->staff->middle_name ?? '') . ' ' . $advance->staff->last_name);
        }
        if ($advance->creator) {
            $advance->creator->name = trim($advance->creator->first_name . ' ' . $advance->creator->last_name);
        }
        if ($advance->approver) {
            $advance->approver->name = trim($advance->approver->first_name . ' ' . $advance->approver->last_name);
        }

        return response()->json([
            'status' => 'success',
            'data' => $advance,
        ]);
    }

    /**
     * Create a new advance
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'type' => 'required|in:advance,loan',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'installments' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();

        $advance = StaffAdvance::create([
            ...$validated,
            'status' => 'pending',
            'remaining_amount' => $validated['amount'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Advance created successfully',
            'data' => $advance->load(['staff', 'creator']),
        ], 201);
    }

    /**
     * Update an advance
     */
    public function update(Request $request, $id)
    {
        $advance = StaffAdvance::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'date' => 'sometimes|date',
            'type' => 'sometimes|in:advance,loan',
            'status' => 'sometimes|in:pending,approved,rejected,paid,partially_paid,completed',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'installments' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        $validated['updated_by'] = $user->id;

        // If amount changed, recalculate remaining
        if (isset($validated['amount'])) {
            $validated['remaining_amount'] = $validated['amount'] - $advance->amount_paid;
        }

        $advance->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Advance updated successfully',
            'data' => $advance->load(['staff', 'creator', 'approver']),
        ]);
    }

    /**
     * Delete an advance
     */
    public function destroy($id)
    {
        $advance = StaffAdvance::findOrFail($id);

        // Don't allow deletion if any deductions have been made
        if ($advance->deductions()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete advance with existing deductions',
            ], 400);
        }

        $advance->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Advance deleted successfully',
        ]);
    }

    /**
     * Approve an advance
     */
    public function approve(Request $request, $id)
    {
        $advance = StaffAdvance::findOrFail($id);

        if ($advance->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending advances can be approved',
            ], 400);
        }

        $user = $request->user();

        $advance->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Advance approved successfully',
            'data' => $advance->load(['staff', 'approver']),
        ]);
    }

    /**
     * Reject an advance
     */
    public function reject(Request $request, $id)
    {
        $advance = StaffAdvance::findOrFail($id);

        if ($advance->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending advances can be rejected',
            ], 400);
        }

        $user = $request->user();

        $advance->update([
            'status' => 'rejected',
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Advance rejected successfully',
            'data' => $advance,
        ]);
    }

    /**
     * Get active advances for a staff member
     */
    public function getActiveAdvances($staffId)
    {
        $advances = StaffAdvance::where('staff_id', $staffId)
            ->whereIn('status', ['approved', 'partially_paid'])
            ->where('remaining_amount', '>', 0)
            ->with('deductions')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $advances,
        ]);
    }
}
