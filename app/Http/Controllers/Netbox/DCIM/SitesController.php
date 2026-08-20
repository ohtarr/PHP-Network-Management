<?php

namespace App\Http\Controllers\Netbox\DCIM;

use App\Http\Controllers\Controller;
use App\Models\Netbox\DCIM\Sites;
use Illuminate\Http\Request;

class SitesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * @OA\Get(
     *     path="/netbox/sites",
     *     summary="Get a list of Netbox DCIM sites",
     *     tags={"Netbox Sites"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Maximum number of results to return (default: 50)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         required=false,
     *         description="Number of results to skip for pagination (default: 0)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="brief",
     *         in="query",
     *         required=false,
     *         description="Return brief results with only id, url, display, name, and slug fields (pass 1)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=false,
     *         description="Free-text search across site fields",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         required=false,
     *         description="Filter by exact site name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name__ie",
     *         in="query",
     *         required=false,
     *         description="Filter by site name exact match (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name__ic",
     *         in="query",
     *         required=false,
     *         description="Filter by site name containing value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name__nic",
     *         in="query",
     *         required=false,
     *         description="Filter by site name NOT containing value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name__isw",
     *         in="query",
     *         required=false,
     *         description="Filter by site name starting with value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name__nisw",
     *         in="query",
     *         required=false,
     *         description="Filter by site name NOT starting with value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name__iew",
     *         in="query",
     *         required=false,
     *         description="Filter by site name ending with value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name__niew",
     *         in="query",
     *         required=false,
     *         description="Filter by site name NOT ending with value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name__n",
     *         in="query",
     *         required=false,
     *         description="Filter by site name NOT equal to value",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="name__empty",
     *         in="query",
     *         required=false,
     *         description="Filter sites where name is empty/null (pass true)",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="slug",
     *         in="query",
     *         required=false,
     *         description="Filter by site slug (exact match)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="slug__ie",
     *         in="query",
     *         required=false,
     *         description="Filter by slug exact match (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="slug__ic",
     *         in="query",
     *         required=false,
     *         description="Filter by slug containing value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="slug__nic",
     *         in="query",
     *         required=false,
     *         description="Filter by slug NOT containing value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="slug__isw",
     *         in="query",
     *         required=false,
     *         description="Filter by slug starting with value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="slug__nisw",
     *         in="query",
     *         required=false,
     *         description="Filter by slug NOT starting with value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="slug__iew",
     *         in="query",
     *         required=false,
     *         description="Filter by slug ending with value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="slug__niew",
     *         in="query",
     *         required=false,
     *         description="Filter by slug NOT ending with value (case-insensitive)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="slug__n",
     *         in="query",
     *         required=false,
     *         description="Filter by slug NOT equal to value",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="slug__empty",
     *         in="query",
     *         required=false,
     *         description="Filter sites where slug is empty/null (pass true)",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by site status (active, planned, staging, decommissioning, retired)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status__n",
     *         in="query",
     *         required=false,
     *         description="Filter by site status NOT equal to value",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="region_id",
     *         in="query",
     *         required=false,
     *         description="Filter by region ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="region_id__n",
     *         in="query",
     *         required=false,
     *         description="Filter by region ID NOT equal to value",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="group_id",
     *         in="query",
     *         required=false,
     *         description="Filter by site group ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="group_id__n",
     *         in="query",
     *         required=false,
     *         description="Filter by site group ID NOT equal to value",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="tag",
     *         in="query",
     *         required=false,
     *         description="Filter by tag slug",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="tag__n",
     *         in="query",
     *         required=false,
     *         description="Filter by tag slug NOT equal to value",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of sites",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Sites::getQuery();

        $filters = [
            'limit', 'offset', 'brief',
            'q',
            // name lookups
            'name', 'name__ie', 'name__ic', 'name__nic',
            'name__isw', 'name__nisw', 'name__iew', 'name__niew', 'name__n', 'name__empty',
            // slug lookups
            'slug', 'slug__ie', 'slug__ic', 'slug__nic',
            'slug__isw', 'slug__nisw', 'slug__iew', 'slug__niew', 'slug__n', 'slug__empty',
            // status
            'status', 'status__n',
            // region
            'region_id', 'region_id__n',
            // group
            'group_id', 'group_id__n',
            // tag
            'tag', 'tag__n',
        ];

        foreach ($filters as $filter) {
            if ($request->has($filter)) {
                $query->where($filter, $request->get($filter));
            }
        }

        return response()->json($query->get());
    }

    /**
     * @OA\Get(
     *     path="/netbox/sites/{id}",
     *     summary="Get a single Netbox DCIM site by ID",
     *     tags={"Netbox Sites"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The site ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Site object",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Site not found")
     * )
     */
    public function show($id)
    {
        $site = Sites::find($id);

        if (!$site || !isset($site->id)) {
            return response()->json(['message' => 'Site not found'], 404);
        }

        return response()->json($site);
    }

    /**
     * @OA\Post(
     *     path="/netbox/sites",
     *     summary="Create a new Netbox DCIM site",
     *     tags={"Netbox Sites"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Site fields to create")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Site created successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $site = Sites::create($request->all());

        return response()->json($site, 201);
    }

    /**
     * @OA\Put(
     *     path="/netbox/sites/{id}",
     *     summary="Update a Netbox DCIM site by ID",
     *     tags={"Netbox Sites"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The site ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Site fields to update")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Update result",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Site not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $site = Sites::find($id);

        if (!$site || !isset($site->id)) {
            return response()->json(['message' => 'Site not found'], 404);
        }

        $updated = $site->update($request->all());

        return response()->json($updated);
    }

    /**
     * @OA\Delete(
     *     path="/netbox/sites/{id}",
     *     summary="Delete a Netbox DCIM site by ID",
     *     tags={"Netbox Sites"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The site ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Site deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Site deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Site not found")
     * )
     */
    public function destroy($id)
    {
        $site = Sites::find($id);

        if (!$site || !isset($site->id)) {
            return response()->json(['message' => 'Site not found'], 404);
        }

        $site->delete();

        return response()->json(['message' => 'Site deleted successfully'], 200);
    }
}
