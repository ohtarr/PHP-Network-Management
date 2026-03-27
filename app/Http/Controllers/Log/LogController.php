<?php

namespace App\Http\Controllers\Log;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Log\Log;

class LogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Display a paginated listing of log entries.
     *
     * Optional query parameters:
     *   ?hours=24              — return logs from the last N hours
     *   ?days=7                — return logs from the last N days
     *   ?controller=Foo        — filter by controller name (partial, case-insensitive)
     *   ?status=1              — filter by status (1 = success, 0 = failure)
     *   ?per_page=25           — number of results per page (default: 25)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Log::orderBy('created_at', 'desc');

        // Time window filters (hours takes precedence over days)
        if ($request->has('hours')) {
            $query->where('created_at', '>=', now()->subHours((int) $request->hours));
        } elseif ($request->has('days')) {
            $query->where('created_at', '>=', now()->subDays((int) $request->days));
        }

        // Filter by controller (partial, case-insensitive)
        if ($request->has('controller')) {
            $query->where('controller', 'like', '%' . $request->controller . '%');
        }

        // Filter by status (boolean: 1 = success, 0 = failure)
        if ($request->has('status')) {
            $query->where('status', (bool) $request->status);
        }

        $perPage = (int) $request->get('per_page', 25);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Display a single log entry by ID.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return response()->json(Log::findOrFail($id));
    }
}
