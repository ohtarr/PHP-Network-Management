<?php

namespace App\Http\Controllers\Netbox\Devices;

use App\Http\Controllers\Controller;
use App\Models\Netbox\DCIM\Devices;
use Illuminate\Http\Request;

class DevicesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * @OA\Get(
     *     path="/netbox/devices",
     *     summary="Get a list of Netbox DCIM devices",
     *     tags={"Netbox Devices"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Maximum number of results to return (default: 50)",
     *         @OA\Schema(type="integer", example=50)
     *     ),
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         required=false,
     *         description="Number of results to skip for pagination (default: 0)",
     *         @OA\Schema(type="integer", example=0)
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=false,
     *         description="Free-text search across device fields",
     *         @OA\Schema(type="string", example="core-switch")
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         required=false,
     *         description="Filter by exact device name",
     *         @OA\Schema(type="string", example="core-sw-01")
     *     ),
     *     @OA\Parameter(
     *         name="name__ic",
     *         in="query",
     *         required=false,
     *         description="Filter by device name containing value (case-insensitive)",
     *         @OA\Schema(type="string", example="core")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by device status (active, planned, staged, failed, inventory, decommissioning, offline)",
     *         @OA\Schema(type="string", example="active")
     *     ),
     *     @OA\Parameter(
     *         name="site",
     *         in="query",
     *         required=false,
     *         description="Filter by site slug",
     *         @OA\Schema(type="string", example="nyc-dc1")
     *     ),
     *     @OA\Parameter(
     *         name="site_id",
     *         in="query",
     *         required=false,
     *         description="Filter by site ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="location_id",
     *         in="query",
     *         required=false,
     *         description="Filter by location ID",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Parameter(
     *         name="rack_id",
     *         in="query",
     *         required=false,
     *         description="Filter by rack ID",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         required=false,
     *         description="Filter by device role slug",
     *         @OA\Schema(type="string", example="core-switch")
     *     ),
     *     @OA\Parameter(
     *         name="role_id",
     *         in="query",
     *         required=false,
     *         description="Filter by device role ID",
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Parameter(
     *         name="device_type_id",
     *         in="query",
     *         required=false,
     *         description="Filter by device type ID",
     *         @OA\Schema(type="integer", example=7)
     *     ),
     *     @OA\Parameter(
     *         name="manufacturer_id",
     *         in="query",
     *         required=false,
     *         description="Filter by manufacturer ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="platform_id",
     *         in="query",
     *         required=false,
     *         description="Filter by platform ID",
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Parameter(
     *         name="virtual_chassis_id",
     *         in="query",
     *         required=false,
     *         description="Filter by virtual chassis ID",
     *         @OA\Schema(type="integer", example=6)
     *     ),
     *     @OA\Parameter(
     *         name="tag",
     *         in="query",
     *         required=false,
     *         description="Filter by tag slug",
     *         @OA\Schema(type="string", example="managed")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of devices",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function index(Request $request)
    {
        $limit  = (int) $request->get('limit', 50);
        $offset = (int) $request->get('offset', 0);

        $query = Devices::limit($limit)->offset($offset);

        $filters = [
            'q',
            'name',
            'name__ic',
            'status',
            'site',
            'site_id',
            'location_id',
            'rack_id',
            'role',
            'role_id',
            'device_type_id',
            'manufacturer_id',
            'platform_id',
            'virtual_chassis_id',
            'tag',
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
     *     path="/netbox/devices/{id}",
     *     summary="Get a single Netbox DCIM device by ID",
     *     tags={"Netbox Devices"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The device ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Device object",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Device not found")
     * )
     */
    public function show($id)
    {
        $device = Devices::find($id);

        if (!$device || !isset($device->id)) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        return response()->json($device);
    }

    /**
     * @OA\Post(
     *     path="/netbox/devices",
     *     summary="Create a new Netbox DCIM device",
     *     tags={"Netbox Devices"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Device fields to create")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Device created successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $device = Devices::create($request->all());

        return response()->json($device, 201);
    }

    /**
     * @OA\Put(
     *     path="/netbox/devices/{id}",
     *     summary="Update a Netbox DCIM device by ID",
     *     tags={"Netbox Devices"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The device ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Device fields to update")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Update result",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Device not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $device = Devices::find($id);

        if (!$device || !isset($device->id)) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $updated = $device->update($request->all());

        return response()->json($updated);
    }

    /**
     * @OA\Delete(
     *     path="/netbox/devices/{id}",
     *     summary="Delete a Netbox DCIM device by ID",
     *     tags={"Netbox Devices"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The device ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Device deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Device deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Device not found")
     * )
     */
    public function destroy($id)
    {
        $device = Devices::find($id);

        if (!$device || !isset($device->id)) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $device->delete();

        return response()->json(['message' => 'Device deleted successfully'], 200);
    }
}
