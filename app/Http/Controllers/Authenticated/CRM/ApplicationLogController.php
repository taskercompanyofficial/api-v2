<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\ApplicationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationLogController extends Controller
{
    /**
     * Get all application logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApplicationLog::query()->orderBy('created_at', 'desc');

        // Filter by level
        if ($request->has('level')) {
            $query->level($request->level);
        }

        // Filter by channel
        if ($request->has('channel')) {
            $query->channel($request->channel);
        }

        // Filter by request ID
        if ($request->has('request_id')) {
            $query->byRequestId($request->request_id);
        }

        // Filter errors only
        if ($request->boolean('errors_only')) {
            $query->errors();
        }

        // Filter warnings only
        if ($request->boolean('warnings_only')) {
            $query->warnings();
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        // Filter recent logs (last 24 hours by default)
        if ($request->has('recent_hours')) {
            $query->recent($request->recent_hours);
        }

        // Search in message
        if ($request->has('search')) {
            $query->where('message', 'like', '%' . $request->search . '%');
        }

        $logs = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'status' => 'success',
            'data' => $logs,
        ]);
    }

    /**
     * Get a specific log entry.
     */
    public function show(int $id): JsonResponse
    {
        $log = ApplicationLog::findOrFail($id);

        return response()->json($log);
    }

    /**
     * Get logs by request ID (all related logs).
     */
    public function byRequestId(string $requestId): JsonResponse
    {
        $logs = ApplicationLog::byRequestId($requestId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'request_id' => $requestId,
            'count' => $logs->count(),
            'logs' => $logs,
        ]);
    }

    /**
     * Get log statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $hours = $request->get('hours', 24);

        $stats = [
            'total' => ApplicationLog::recent($hours)->count(),
            'by_level' => [
                'emergency' => ApplicationLog::recent($hours)->level('emergency')->count(),
                'alert' => ApplicationLog::recent($hours)->level('alert')->count(),
                'critical' => ApplicationLog::recent($hours)->level('critical')->count(),
                'error' => ApplicationLog::recent($hours)->level('error')->count(),
                'warning' => ApplicationLog::recent($hours)->level('warning')->count(),
                'notice' => ApplicationLog::recent($hours)->level('notice')->count(),
                'info' => ApplicationLog::recent($hours)->level('info')->count(),
                'debug' => ApplicationLog::recent($hours)->level('debug')->count(),
            ],
            'by_channel' => ApplicationLog::recent($hours)
                ->selectRaw('channel, COUNT(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel'),
            'errors_total' => ApplicationLog::recent($hours)->errors()->count(),
            'warnings_total' => ApplicationLog::recent($hours)->warnings()->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Delete old logs.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        
        $deleted = ApplicationLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} log entries older than {$days} days",
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Delete a specific log entry.
     */
    public function destroy(int $id): JsonResponse
    {
        $log = ApplicationLog::findOrFail($id);
        $log->delete();

        return response()->json([
            'success' => true,
            'message' => 'Log entry deleted successfully',
        ]);
    }
}
