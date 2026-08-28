<?php

namespace App\Http\Controllers\Dhcp;

use App\Http\Controllers\Controller;
use App\Models\Dhcp\SubnetV4;
use App\Models\Dhcp\ReservationV4;
use App\Models\Dhcp\LeaseV4;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Gizmo\Dhcp as GizmoDhcp;
use App\Models\Netbox\DCIM\Sites;

class DhcpController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    private function logAccess(string $message): void
    {
        Log::info($message, ['user' => auth()->user()?->userPrincipalName]);
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
        $this->logAccess("Accessed subnetv4 index (id={$request->query('id')}, subnet={$request->query('subnet')}, mask={$request->query('mask')})");

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
        $this->logAccess("Accessed subnetv4 store");

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
        $this->logAccess("Accessed subnetv4 destroyById (id={$id})");

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
        $this->logAccess("Accessed subnetv4 destroyBySubnet (subnet={$subnet}, length={$length})");

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
        $this->logAccess("Accessed sitesummary (sitecode={$sitecode})");

        $site = Sites::where('name__ie', $sitecode)->first();

        if (!$site || !isset($site->id)) {
            return response()->json(['message' => 'Site not found'], 404);
        }

        return response()->json($site->getAllScopes() ?: []);
    }

    /**
     * @OA\Get(
     *     path="/dhcp/reservationv4",
     *     summary="Get DHCPv4 reservations, filtered by ip, mac, or subnet",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="ip",
     *         in="query",
     *         required=false,
     *         description="Filter by reservation IP address",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="mac",
     *         in="query",
     *         required=false,
     *         description="Filter by reservation MAC address",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="subnet",
     *         in="query",
     *         required=false,
     *         description="Filter by subnet network address, returning all reservations in it",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of DHCPv4 reservations",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     ),
     *     @OA\Response(response=422, description="One of ip, mac, or subnet is required")
     * )
     */
    public function reservationIndex(Request $request)
    {
        $this->logAccess("Accessed reservationv4 index (ip={$request->query('ip')}, mac={$request->query('mac')}, subnet={$request->query('subnet')})");

        if ($request->filled('ip')) {
            $reservation = ReservationV4::findByIp($request->query('ip'));
            return response()->json($reservation ? collect([$reservation]) : collect());
        }

        if ($request->filled('mac')) {
            $reservation = ReservationV4::findByMac($request->query('mac'));
            return response()->json($reservation ? collect([$reservation]) : collect());
        }

        if ($request->filled('subnet')) {
            return response()->json(ReservationV4::allBySubnet($request->query('subnet')));
        }

        return response()->json(['message' => 'One of ip, mac, or subnet is required'], 422);
    }

    /**
     * @OA\Post(
     *     path="/dhcp/reservationv4",
     *     summary="Create a new DHCPv4 reservation",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ipaddress","hwaddress","description"},
     *             @OA\Property(property="ipaddress", type="string", example="10.1.2.3"),
     *             @OA\Property(property="hwaddress", type="string", example="aa:bb:cc:dd:ee:ff"),
     *             @OA\Property(property="description", type="string", example="Printer in room 204")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Reservation created successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function reservationStore(Request $request)
    {
        $this->logAccess("Accessed reservationv4 store (ipaddress={$request->input('ipaddress')})");

        $validated = $request->validate([
            'ipaddress' => 'required|string',
            'hwaddress' => 'required|string',
            'description' => 'required|string',
        ]);

        $reservation = ReservationV4::create($validated['ipaddress'], $validated['hwaddress'], $validated['description']);

        return response()->json($reservation, 201);
    }

    /**
     * @OA\Patch(
     *     path="/dhcp/reservationv4",
     *     summary="Update an existing DHCPv4 reservation",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ipaddress","hwaddress","description"},
     *             @OA\Property(property="ipaddress", type="string", example="10.1.2.3"),
     *             @OA\Property(property="hwaddress", type="string", example="aa:bb:cc:dd:ee:ff"),
     *             @OA\Property(property="description", type="string", example="Printer in room 204")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Update result",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function reservationUpdate(Request $request)
    {
        $this->logAccess("Accessed reservationv4 update (ipaddress={$request->input('ipaddress')})");

        $validated = $request->validate([
            'ipaddress' => 'required|string',
            'hwaddress' => 'required|string',
            'description' => 'required|string',
        ]);

        $reservation = ReservationV4::update($validated['ipaddress'], $validated['hwaddress'], $validated['description']);

        return response()->json($reservation);
    }

    /**
     * @OA\Delete(
     *     path="/dhcp/reservationv4/ip/{ip}",
     *     summary="Delete a DHCPv4 reservation by IP address",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="ip",
     *         in="path",
     *         required=true,
     *         description="The reservation's IP address",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reservation deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Reservation deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Reservation not found")
     * )
     */
    public function reservationDestroyByIp($ip)
    {
        $this->logAccess("Accessed reservationv4 destroyByIp (ip={$ip})");

        $reservation = ReservationV4::findByIp($ip);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        ReservationV4::deleteByIp($ip);

        return response()->json(['message' => 'Reservation deleted successfully'], 200);
    }

    /**
     * @OA\Delete(
     *     path="/dhcp/reservationv4/mac/{mac}",
     *     summary="Delete a DHCPv4 reservation by MAC address",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="mac",
     *         in="path",
     *         required=true,
     *         description="The reservation's MAC address",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reservation deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Reservation deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Reservation not found")
     * )
     */
    public function reservationDestroyByMac($mac)
    {
        $this->logAccess("Accessed reservationv4 destroyByMac (mac={$mac})");

        $reservation = ReservationV4::findByMac($mac);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        ReservationV4::deleteByMac($mac);

        return response()->json(['message' => 'Reservation deleted successfully'], 200);
    }

    /**
     * @OA\Get(
     *     path="/dhcp/leasev4",
     *     summary="Get DHCPv4 leases, filtered by ip, mac, or subnet",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="ip",
     *         in="query",
     *         required=false,
     *         description="Filter by lease IP address",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="mac",
     *         in="query",
     *         required=false,
     *         description="Filter by lease MAC address",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="subnet",
     *         in="query",
     *         required=false,
     *         description="Filter by subnet network address, returning all leases in it",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of DHCPv4 leases",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     ),
     *     @OA\Response(response=422, description="One of ip, mac, or subnet is required")
     * )
     */
    public function leaseIndex(Request $request)
    {
        $this->logAccess("Accessed leasev4 index (ip={$request->query('ip')}, mac={$request->query('mac')}, subnet={$request->query('subnet')})");

        if ($request->filled('ip')) {
            $lease = LeaseV4::findByIp($request->query('ip'));
            return response()->json($lease ? collect([$lease]) : collect());
        }

        if ($request->filled('mac')) {
            $lease = LeaseV4::findByMac($request->query('mac'));
            return response()->json($lease ? collect([$lease]) : collect());
        }

        if ($request->filled('subnet')) {
            return response()->json(LeaseV4::allBySubnet($request->query('subnet')));
        }

        return response()->json(['message' => 'One of ip, mac, or subnet is required'], 422);
    }

    /**
     * @OA\Post(
     *     path="/dhcp/leasev4",
     *     summary="Create a new DHCPv4 lease",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ipaddress","hwaddress","hostname"},
     *             @OA\Property(property="ipaddress", type="string", example="10.1.2.3"),
     *             @OA\Property(property="hwaddress", type="string", example="aa:bb:cc:dd:ee:ff"),
     *             @OA\Property(property="hostname", type="string", example="printer-204")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lease created successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function leaseStore(Request $request)
    {
        $this->logAccess("Accessed leasev4 store (ipaddress={$request->input('ipaddress')})");

        $validated = $request->validate([
            'ipaddress' => 'required|string',
            'hwaddress' => 'required|string',
            'hostname' => 'required|string',
        ]);

        $lease = LeaseV4::create($validated['ipaddress'], $validated['hwaddress'], $validated['hostname']);

        return response()->json($lease, 201);
    }

    /**
     * @OA\Patch(
     *     path="/dhcp/leasev4",
     *     summary="Update an existing DHCPv4 lease",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ipaddress","hwaddress","hostname"},
     *             @OA\Property(property="ipaddress", type="string", example="10.1.2.3"),
     *             @OA\Property(property="hwaddress", type="string", example="aa:bb:cc:dd:ee:ff"),
     *             @OA\Property(property="hostname", type="string", example="printer-204")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Update result",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function leaseUpdate(Request $request)
    {
        $this->logAccess("Accessed leasev4 update (ipaddress={$request->input('ipaddress')})");

        $validated = $request->validate([
            'ipaddress' => 'required|string',
            'hwaddress' => 'required|string',
            'hostname' => 'required|string',
        ]);

        $lease = LeaseV4::update($validated['ipaddress'], $validated['hwaddress'], $validated['hostname']);

        return response()->json($lease);
    }

    /**
     * @OA\Delete(
     *     path="/dhcp/leasev4/ip/{ip}",
     *     summary="Delete a DHCPv4 lease by IP address",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="ip",
     *         in="path",
     *         required=true,
     *         description="The lease's IP address",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lease deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Lease deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Lease not found")
     * )
     */
    public function leaseDestroyByIp($ip)
    {
        $this->logAccess("Accessed leasev4 destroyByIp (ip={$ip})");

        $lease = LeaseV4::findByIp($ip);

        if (!$lease) {
            return response()->json(['message' => 'Lease not found'], 404);
        }

        LeaseV4::deleteByIp($ip);

        return response()->json(['message' => 'Lease deleted successfully'], 200);
    }

    /**
     * @OA\Get(
     *     path="/dhcp/gizmo",
     *     summary="Get Gizmo DHCP scopes, optionally filtered by id, sitecode, or containing ip",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         required=false,
     *         description="Filter by Gizmo scope ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sitecode",
     *         in="query",
     *         required=false,
     *         description="Filter by site code",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="ip",
     *         in="query",
     *         required=false,
     *         description="Filter by IP address contained within a scope",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of Gizmo DHCP scopes",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function gizmoIndex(Request $request)
    {
        $this->logAccess("Accessed gizmo index (id={$request->query('id')}, sitecode={$request->query('sitecode')}, ip={$request->query('ip')})");

        if ($request->filled('id')) {
            $scope = GizmoDhcp::find($request->query('id'));
            return response()->json($scope ? collect([$scope]) : collect());
        }

        if ($request->filled('sitecode')) {
            return response()->json(GizmoDhcp::getScopesBySitecode($request->query('sitecode')));
        }

        if ($request->filled('ip')) {
            $scope = GizmoDhcp::findScopeByIp($request->query('ip'));
            return response()->json($scope ? collect([$scope]) : collect());
        }

        return response()->json(GizmoDhcp::all());
    }

    /**
     * @OA\Get(
     *     path="/dhcp/gizmo/{id}/reservations",
     *     summary="Get reservations for a Gizmo DHCP scope",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The Gizmo scope ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of reservations",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     ),
     *     @OA\Response(response=404, description="Scope not found")
     * )
     */
    public function gizmoReservations($id)
    {
        $this->logAccess("Accessed gizmo reservations (id={$id})");

        $scope = GizmoDhcp::find($id);

        if (!$scope) {
            return response()->json(['message' => 'Scope not found'], 404);
        }

        return response()->json($scope->getReservations());
    }

    /**
     * @OA\Get(
     *     path="/dhcp/gizmo/{id}/leases",
     *     summary="Get leases for a Gizmo DHCP scope",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The Gizmo scope ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of leases",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     ),
     *     @OA\Response(response=404, description="Scope not found")
     * )
     */
    public function gizmoLeases($id)
    {
        $this->logAccess("Accessed gizmo leases (id={$id})");

        $scope = GizmoDhcp::find($id);

        if (!$scope) {
            return response()->json(['message' => 'Scope not found'], 404);
        }

        return response()->json($scope->getLeases());
    }

    /**
     * @OA\Get(
     *     path="/dhcp/gizmo/overlap/{network}/{bitmask}",
     *     summary="Get Gizmo DHCP scopes overlapping a given network range",
     *     tags={"Dhcp"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="network",
     *         in="path",
     *         required=true,
     *         description="The network address (e.g. 10.1.2.0)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="bitmask",
     *         in="path",
     *         required=true,
     *         description="The prefix length (e.g. 24)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of overlapping Gizmo DHCP scopes",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function gizmoOverlap($network, $bitmask)
    {
        $this->logAccess("Accessed gizmo overlap (network={$network}, bitmask={$bitmask})");

        return response()->json(GizmoDhcp::findOverlap($network, $bitmask));
    }
}
