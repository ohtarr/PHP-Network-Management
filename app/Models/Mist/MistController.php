<?php

namespace App\Models\Mist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mist\Site;
use App\Models\Mist\Device;
use Illuminate\Support\Facades\Log;

class MistController extends Controller
{
    public function __construct()
    {
	    $this->middleware('auth:api');
    }

    public function Sites()
    {
        $user = auth()->user();
		if ($user->cant('read', Site::class)) {
			abort(401, 'You are not authorized');
        }
        return Site::all();
    }

    public function SitesSummary()
    {
        $user = auth()->user();
		if ($user->cant('read', Site::class)) {
			abort(401, 'You are not authorized');
        }
        return Site::getAllSummarized();
    }

        /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function Inventory()
    {
        $user = auth()->user();
		if ($user->cant('read', Device::class)) {
			abort(401, 'You are not authorized');
        }
        return Device::all();
    }

    public function SiteDeviceSummary(Request $request, string $siteid, string $type = "all")
    {
        $user = auth()->user();
		if ($user->cant('read', Device::class) || $user->cant('read', Site::class)) {
			abort(401, 'You are not authorized');
        }
        if($request->get('type'))
        {
            $type = $request->get('type');
        }
        $site = Site::find($siteid);
        return $site->getDeviceSummary($type);
    }

    /**
     * @OA\Get(
     *     path="/mist/site/{siteid}/devices",
     *     summary="Get all Mist devices for a site",
     *     tags={"Mist"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="siteid",
     *         in="path",
     *         required=true,
     *         description="The Mist site ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="Device type to filter by (e.g. ap, switch, gateway); defaults to all",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of devices for the site",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function SiteDevices(Request $request, string $siteid, string $type = "all")
    {
        $user = auth()->user();
		if ($user->cant('read', Device::class) || $user->cant('read', Site::class)) {
			abort(401, 'You are not authorized');
        }
        if($request->get('type'))
        {
            $type = $request->get('type');
        }
        $site = Site::find($siteid);
        return $site->getDevices($type);
    }

    public function SiteDevice($siteid, $deviceid)
    {
        $user = auth()->user();
		if ($user->cant('read', Device::class)) {
			abort(401, 'You are not authorized');
        }
        return Device::find($deviceid);
    }

    public function SiteDeviceStats($siteid, $deviceid)
    {
        $user = auth()->user();
		if ($user->cant('read', Device::class)) {
			abort(401, 'You are not authorized');
        }
        return Device::find($deviceid);
    }

    public function SiteDeviceSummaryDetails($siteid, $deviceid)
    {
        $user = auth()->user();
        if ($user->cant('read', Device::class) || $user->cant('read', Site::class)) {
			abort(401, 'You are not authorized');
        }
        $device = new Device;
        $device->site_id = $siteid;
        $device->id = $deviceid;
        return $device->getSummaryDetails();
    }

    public function claimDevices(Request $request, $sitecode = null)
    {
        Log::info(auth()->user()->userPrincipalName . " : " . __CLASS__ . " : " . __FUNCTION__ . ": REQUEST: " . $request->getContent());
        $response = Device::claimDevices($request->all());
        Log::info(auth()->user()->userPrincipalName . " : " . __CLASS__ . " : " . __FUNCTION__ . ": RESPONSE: " . serialize($response));
        return $response;
    }

    public function getWirelessClientStats($siteid)
    {
        $site = Site::find($siteid);
        if(isset($site->id))
        {
            return $site->getWirelessClientStats();
        }
    }

    public function getWiredClientStats($siteid)
    {
        $site = Site::find($siteid);
        if(isset($site->id))
        {
            return $site->getWiredClientStats();
        }
    }

    /**
     * @OA\Get(
     *     path="/mist/device/serial/{serial}",
     *     summary="Get a Mist device by serial number",
     *     tags={"Mist"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="serial",
     *         in="path",
     *         required=true,
     *         description="The device serial number to look up",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Device matching the serial number",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function DeviceBySerial($serial)
    {
        $user = auth()->user();
		if ($user->cant('read', Device::class)) {
			abort(401, 'You are not authorized');
        }
        return Device::findBySerial($serial);
    }

    /**
     * @OA\Get(
     *     path="/mist/device/mac/{mac}",
     *     summary="Get a Mist device by MAC address",
     *     tags={"Mist"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="mac",
     *         in="path",
     *         required=true,
     *         description="The device MAC address to look up",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Device matching the MAC address",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function DeviceByMac($mac)
    {
        $user = auth()->user();
		if ($user->cant('read', Device::class)) {
			abort(401, 'You are not authorized');
        }
        return Device::findByMac($mac);
    }

    /**
     * @OA\Get(
     *     path="/mist/device/{id}",
     *     summary="Get a Mist device by ID",
     *     tags={"Mist"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The Mist device ID to look up",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Device matching the ID",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function DeviceById($id)
    {
        $user = auth()->user();
		if ($user->cant('read', Device::class)) {
			abort(401, 'You are not authorized');
        }
        return Device::findById($id);
    }

}
