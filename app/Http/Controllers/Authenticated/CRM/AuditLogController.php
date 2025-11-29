<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\QueryFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuditLogController extends Controller
{
    use QueryFilterTrait;

    protected $allowedFilters = [
        'table_name',
        'record_id',
        'action',
        'user_id',
        'user_name',
        'user_role'
    ];

    protected $allowedSorts = [
        'id',
        'table_name',
        'record_id',
        'action',
        'user_name',
        'created_at'
    ];

    public function index(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $perPage = $request->input('perPage') ?? 10;
        
        $query = AuditLog::query();
        
        $this->applyJsonFilters($query, $request);
        $this->applySorting($query, $request);
        
        $this->applyUrlFilters($query, $request, [
            'table_name', 'record_id', 'action', 'user_id', 'user_name', 'user_role'
        ]);
        
        $auditLogs = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['status' => 'success', 'data' => $auditLogs]);
    }

    public function show(AuditLog $auditLog)
    {
        return response()->json([
            'success' => true,
            'data' => $auditLog
        ]);
    }

    public function destroy(AuditLog $auditLog)
    {
        // Audit logs should generally not be deleted, but we'll allow it for admin purposes
        $auditLog->delete();

        return response()->json([
            'success' => true,
            'message' => 'Audit log deleted successfully'
        ]);
    }
}