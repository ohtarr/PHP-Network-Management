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

}
