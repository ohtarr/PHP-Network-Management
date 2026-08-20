<?php

namespace App\Http\Controllers\Netbox\DCIM;

use App\Http\Controllers\Controller;
use App\Models\Netbox\IPAM\Prefixes;
use Illuminate\Http\Request;

class PrefixesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * @OA\Get(
     *     path="/netbox/prefixes",
     *     summary="Get a list of Netbox IPAM prefixes",
     *     tags={"Netbox Prefixes"},
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
     *         name="q",
     *         in="query",
     *         required=false,
     *         description="Free-text search across prefix fields",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="prefix",
     *         in="query",
     *         required=false,
     *         description="Filter by exact prefix (CIDR notation)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="within",
     *         in="query",
     *         required=false,
     *         description="Filter for prefixes within the given CIDR range",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="within_include",
     *         in="query",
     *         required=false,
     *         description="Filter for prefixes within (and including) the given CIDR range",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="contains",
     *         in="query",
     *         required=false,
     *         description="Filter for prefixes containing the given IP or CIDR",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="family",
     *         in="query",
     *         required=false,
     *         description="Filter by address family (4 or 6)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="mask_length",
     *         in="query",
     *         required=false,
     *         description="Filter by mask length",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="depth",
     *         in="query",
     *         required=false,
     *         description="Filter by prefix hierarchy depth",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="vrf_id",
     *         in="query",
     *         required=false,
     *         description="Filter by VRF ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="site_id",
     *         in="query",
     *         required=false,
     *         description="Filter by site ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="role_id",
     *         in="query",
     *         required=false,
     *         description="Filter by role ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by prefix status (container, active, reserved, deprecated)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status__n",
     *         in="query",
     *         required=false,
     *         description="Filter by prefix status NOT equal to value",
     *         @OA\Schema(type="string")
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
     *         description="List of prefixes",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Prefixes::getQuery();

        $filters = [
            'limit', 'offset',
            'q',
            'prefix', 'within', 'within_include', 'contains',
            'family', 'mask_length', 'depth',
            'vrf_id', 'site_id', 'role_id',
            'status', 'status__n',
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
     *     path="/netbox/prefixes/{id}",
     *     summary="Get a single Netbox IPAM prefix by ID",
     *     tags={"Netbox Prefixes"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The prefix ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prefix object",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Prefix not found")
     * )
     */
    public function show($id)
    {
        $prefix = Prefixes::find($id);

        if (!$prefix || !isset($prefix->id)) {
            return response()->json(['message' => 'Prefix not found'], 404);
        }

        return response()->json($prefix);
    }

    /**
     * @OA\Post(
     *     path="/netbox/prefixes",
     *     summary="Create a new Netbox IPAM prefix",
     *     tags={"Netbox Prefixes"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Prefix fields to create")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Prefix created successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $prefix = Prefixes::create($request->all());

        return response()->json($prefix, 201);
    }

    /**
     * @OA\Put(
     *     path="/netbox/prefixes/{id}",
     *     summary="Update a Netbox IPAM prefix by ID",
     *     tags={"Netbox Prefixes"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The prefix ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Prefix fields to update")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Update result",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Prefix not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $prefix = Prefixes::find($id);

        if (!$prefix || !isset($prefix->id)) {
            return response()->json(['message' => 'Prefix not found'], 404);
        }

        $updated = $prefix->update($request->all());

        return response()->json($updated);
    }

    /**
     * @OA\Delete(
     *     path="/netbox/prefixes/{id}",
     *     summary="Delete a Netbox IPAM prefix by ID",
     *     tags={"Netbox Prefixes"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The prefix ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prefix deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Prefix deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Prefix not found")
     * )
     */
    public function destroy($id)
    {
        $prefix = Prefixes::find($id);

        if (!$prefix || !isset($prefix->id)) {
            return response()->json(['message' => 'Prefix not found'], 404);
        }

        $prefix->delete();

        return response()->json(['message' => 'Prefix deleted successfully'], 200);
    }
}
