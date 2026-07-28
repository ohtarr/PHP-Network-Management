<?php

namespace App\Http\Controllers\Output;

use App\Http\Controllers\Controller;
use App\Models\Device\Device;
use App\Models\Device\Output;
use Illuminate\Http\Request;

class OutputController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * @OA\Get(
     *     path="/outputs",
     *     summary="List device outputs",
     *     description="Returns a list of device command outputs. Optionally filter by netbox_id, output type, or retrieve only the latest output per type. When no netbox_id is provided, all outputs are returned paginated.",
     *     tags={"Outputs"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="netbox_id",
     *         in="query",
     *         required=false,
     *         description="Filter outputs by the device's Netbox ID",
     *         @OA\Schema(type="integer", example=42)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="Filter outputs by type (e.g. 'show version')",
     *         @OA\Schema(type="string", example="show version")
     *     ),
     *     @OA\Parameter(
     *         name="latest",
     *         in="query",
     *         required=false,
     *         description="When true and netbox_id is provided, return only the most recent output per type",
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of results per page when paginating (default: 25). Only applies when netbox_id is not provided.",
     *         @OA\Schema(type="integer", example=25)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of output records",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Device not found for the given netbox_id")
     * )
     */
    public function index(Request $request)
    {
        $netboxId = $request->get('netbox_id');
        $type     = $request->get('type');
        $latest   = filter_var($request->get('latest', false), FILTER_VALIDATE_BOOLEAN);
        $perPage  = (int) $request->get('per_page', 25);

        // If a netbox_id is provided, scope outputs to that device
        if ($netboxId !== null) {
            $device = Device::where('netbox_id', $netboxId)->first();

            if (!$device) {
                return response()->json(['message' => 'Device not found for the given netbox_id'], 404);
            }

            // Return only the latest output per type (optionally filtered to one type)
            if ($latest) {
                $outputs = $device->getLatestOutputs($type ?: null);
                return response()->json($outputs);
            }

            // Return all outputs, optionally filtered by type
            $outputs = $device->getAllOutputs($type ?: null);
            return response()->json($outputs);
        }

        // No netbox_id — return all outputs, optionally filtered by type, paginated
        $query = Output::orderBy('id', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * @OA\Get(
     *     path="/outputs/{id}",
     *     summary="Get a single output record by ID",
     *     tags={"Outputs"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The output record ID",
     *         @OA\Schema(type="integer", example=99)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Output record",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Output not found")
     * )
     */
    public function show($id)
    {
        $output = Output::find($id);

        if (!$output) {
            return response()->json(['message' => 'Output not found'], 404);
        }

        return response()->json($output);
    }
}
