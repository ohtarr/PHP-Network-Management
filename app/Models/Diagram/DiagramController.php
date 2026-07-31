<?php

namespace App\Models\Diagram;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Diagram\Diagram;

class DiagramController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * @OA\Get(
     *     path="/diagrams",
     *     tags={"Diagrams"},
     *     summary="List all stored diagrams",
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="site_id",
     *         in="query",
     *         required=false,
     *         description="Filter diagrams by Netbox site ID",
     *         @OA\Schema(type="integer", example=42)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of diagram records",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="SITE01 Network Diagram"),
     *                 @OA\Property(property="type", type="string", example="network"),
     *                 @OA\Property(property="site_id", type="integer", example=42),
     *                 @OA\Property(property="data", type="object", description="Generated diagram payload (nodes, links, unlinked)"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid filter parameter supplied"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     *
     * Display a listing of diagrams.
     *
     * Supports filtering via query parameters listed in $allowedFilters.
     * Returns a 400 error if any unrecognised filter parameter is supplied.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        /* $user = auth()->user();
        if ($user->cant('read', Diagram::class)) {
            abort(401, 'You are not authorized');
        } */

        $allowedFilters = ['site_id'];

        // Reject any query params that are not in the allowed list
        $requestedFilters = array_keys($request->query());
        $invalidFilters   = array_diff($requestedFilters, $allowedFilters);

        if (!empty($invalidFilters)) {
            abort(400, 'Invalid filter(s): ' . implode(', ', $invalidFilters)
                . '. Allowed filters: ' . implode(', ', $allowedFilters));
        }

        $query = Diagram::query();

        foreach ($allowedFilters as $filter) {
            if ($request->has($filter)) {
                $query->where($filter, $request->get($filter));
            }
        }

        return response()->json($query->get());
    }

    /**
     * @OA\Get(
     *     path="/diagrams/{id}",
     *     tags={"Diagrams"},
     *     summary="Retrieve a single diagram by ID",
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The database ID of the diagram",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Diagram record",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="SITE01 Network Diagram"),
     *             @OA\Property(property="type", type="string", example="network"),
     *             @OA\Property(property="site_id", type="integer", example=42),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Generated diagram payload",
     *                 @OA\Property(property="siteId", type="string", example="abc123"),
     *                 @OA\Property(property="nodes", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="links", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="unlinked", type="array", @OA\Items(type="string"))
     *             ),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Diagram not found")
     * )
     *
     * Display a single diagram by its database ID.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function find(Request $request, int $id)
    {
        $user = auth()->user();
        if ($user->cant('read', Diagram::class)) {
            abort(401, 'You are not authorized');
        }

        $diagram = Diagram::findOrFail($id);

        return response()->json($diagram);
    }
}
