<?php

namespace App\Http\Controllers\Dhcp;

use App\Http\Controllers\Controller;
use App\Models\Dhcp\SubnetV4;
use Illuminate\Http\Request;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Gizmo\Dhcp as GizmoDhcp;
use App\Models\Netbox\DCIM\Sites;

class DhcpController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * @OA\Get(
     *     path="/dhcp/subnetv4",
     *     summary="Get DHCPv4 subnets, optionally filtered by id or by prefix+mask",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         required=false,
     *         description="Filter by Kea subnet ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="subnet",
     *         in="query",
     *         required=false,
     *         description="Filter by network address (requires mask)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="mask",
     *         in="query",
     *         required=false,
     *         description="Filter by prefix length (requires subnet)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of DHCPv4 subnets",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     ),
     *     @OA\Response(response=422, description="subnet and mask must be supplied together")
     * )
     */
    public function index(Request $request)
    {
        if ($request->filled('id')) {
            $subnet = SubnetV4::find((int) $request->query('id'));
            return response()->json($subnet ? collect([$subnet]) : collect());
        }

        if ($request->filled('subnet') || $request->filled('mask')) {
            if (!$request->filled('subnet') || !$request->filled('mask')) {
                return response()->json(['message' => 'subnet and mask must be supplied together'], 422);
            }

            $subnet = SubnetV4::findBySubnet($request->query('subnet'), (int) $request->query('mask'));
            return response()->json($subnet ? collect([$subnet]) : collect());
        }

        return response()->json(SubnetV4::all());
    }

    /**
     * @OA\Post(
     *     path="/dhcp/subnetv4",
     *     summary="Create a new DHCPv4 subnet",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Subnet parameters formatted for the Kea DHCP API")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Subnet created successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $subnet = SubnetV4::create($request->all());

        return response()->json($subnet, 201);
    }

    /**
     * @OA\Delete(
     *     path="/dhcp/subnetv4/{id}",
     *     summary="Delete a DHCPv4 subnet by Kea subnet ID",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The Kea subnet ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subnet deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Subnet deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Subnet not found")
     * )
     */
    public function destroyById($id)
    {
        $subnet = SubnetV4::find($id);

        if (!$subnet || !isset($subnet->id)) {
            return response()->json(['message' => 'Subnet not found'], 404);
        }

        SubnetV4::deleteById($id);

        return response()->json(['message' => 'Subnet deleted successfully'], 200);
    }

    /**
     * @OA\Delete(
     *     path="/dhcp/subnetv4/{subnet}/{length}",
     *     summary="Delete a DHCPv4 subnet by network address and prefix length",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="subnet",
     *         in="path",
     *         required=true,
     *         description="The network address (e.g. 10.1.2.0)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="length",
     *         in="path",
     *         required=true,
     *         description="The prefix length (e.g. 24)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subnet deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Subnet deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Subnet not found")
     * )
     */
    public function destroyBySubnet($subnet, $length)
    {
        $found = SubnetV4::findBySubnet($subnet, $length);

        if (!$found || !isset($found->id)) {
            return response()->json(['message' => 'Subnet not found'], 404);
        }

        SubnetV4::deleteBySubnet($subnet, $length);

        return response()->json(['message' => 'Subnet deleted successfully'], 200);
    }

    /**
     * @OA\Get(
     *     path="/dhcp/sitesummary/{sitecode}",
     *     summary="Get aggregated DHCP scope summary for a site (Netbox prefixes, Gizmo scopes, Kea scopes)",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="sitecode",
     *         in="path",
     *         required=true,
     *         description="The Netbox site name/code",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Aggregated DHCP scope summary, keyed by prefix/subnet",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Site not found")
     * )
     */
    public function sitesummary($sitecode)
    {
        $site = Sites::where('name__ie', $sitecode)->first();

        if (!$site || !isset($site->id)) {
            return response()->json(['message' => 'Site not found'], 404);
        }

        return response()->json($site->getAllScopes() ?: []);
    }
}
