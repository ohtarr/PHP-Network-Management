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
     * @OA\Get(
     *     path="/logs",
     *     summary="Get a paginated list of application log entries",
     *     tags={"Logs"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="hours",
     *         in="query",
     *         required=false,
     *         description="Return logs from the last N hours (takes precedence over days)",
     *         @OA\Schema(type="integer", example=24)
     *     ),
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         required=false,
     *         description="Return logs from the last N days",
     *         @OA\Schema(type="integer", example=7)
     *     ),
     *     @OA\Parameter(
     *         name="username",
     *         in="query",
     *         required=false,
     *         description="Filter by username (partial match, case-insensitive)",
     *         @OA\Schema(type="string", example="jsmith")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of results per page (default: 25)",
     *         @OA\Schema(type="integer", example=25)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated log entries",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=100),
     *             @OA\Property(property="per_page", type="integer", example=25)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $query = Log::orderBy('created_at', 'desc');

        if ($request->has('hours')) {
            $query->where('created_at', '>=', now()->subHours((int) $request->hours));
        } elseif ($request->has('days')) {
            $query->where('created_at', '>=', now()->subDays((int) $request->days));
        }

        if ($request->has('username')) {
            $query->where('username', 'like', '%' . $request->username . '%');
        }

        $perPage = (int) $request->get('per_page', 25);

        return response()->json($query->paginate($perPage));
    }

    /**
     * @OA\Get(
     *     path="/logs/{id}",
     *     summary="Get a single log entry by ID",
     *     tags={"Logs"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The log entry ID",
     *         @OA\Schema(type="integer", example=42)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Log entry object",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Log entry not found")
     * )
     */
    public function show($id)
    {
        return response()->json(Log::findOrFail($id));
    }
}
