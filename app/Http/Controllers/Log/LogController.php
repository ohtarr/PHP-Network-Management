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
     *   ?username=jsmith       — filter by username (partial, case-insensitive)
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

        // Filter by username (partial, case-insensitive)
        if ($request->has('username')) {
            $query->where('username', 'like', '%' . $request->username . '%');
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
