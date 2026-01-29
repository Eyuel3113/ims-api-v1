<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * @group Activity Logs
 * APIs for viewing system activity logs
 */
class ActivityLogController extends Controller
{
    /**
     * List Activity Logs
     * 
     * Get paginated system activity logs with filters.
     * 
     * @queryParam log_name string filter by log name (e.g. 'default')
     * @queryParam event string filter by event (created, updated, deleted, etc.)
     * @queryParam subject_type string filter by model class
     * @queryParam causer_id string filter by user ID
     * @queryParam date_from date filter logs from date
     * @queryParam date_to date filter logs to date
     * @queryParam search string search in description
     * @queryParam limit integer pagination limit, default 15
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 15);
        $logName = $request->query('log_name');
        $event = $request->query('event');
        $subjectType = $request->query('subject_type');
        $causerId = $request->query('causer_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $search = $request->query('search');

        $query = Activity::with(['causer', 'subject'])->latest();

        if ($logName) {
            $query->where('log_name', $logName);
        }

        if ($event) {
            $query->where('event', $event);
        }

        if ($subjectType) {
            // Support short names if mapped, but here we expect the full class name or suffix
            $query->where('subject_type', 'like', "%{$subjectType}%");
        }

        if ($causerId) {
            $query->where('causer_id', $causerId);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHasMorph('causer', [\App\Models\User::class], function ($query) use ($search) {
                      $query->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->paginate($limit);

        // Transform the collection to simplify subject_type
        $transformedItems = collect($logs->items())->map(function ($activity) {
            if ($activity->subject_type) {
                $activity->subject_type = class_basename($activity->subject_type);
            }
            return $activity;
        });

        return response()->json([
            'message' => 'Activity logs fetched successfully',
            'data' => $transformedItems,
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ]
        ]);
    }


}
