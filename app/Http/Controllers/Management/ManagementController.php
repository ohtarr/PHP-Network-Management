<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Mist\Site;
use App\Models\Mist\Device;
use Illuminate\Support\Facades\Log;

class ManagementController extends Controller
{
    public $logs = [];
    
    public function __construct()
    {
	    $this->middleware('auth:api');
    }

    public function addLog($status, $msg)
    {
        $this->logs[] = [
            'status'    =>  $status,
            'msg'       =>  $msg,
        ];
        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . debug_backtrace()[1]['function'] . ": " . $msg);
    }

    public function getSiteSummary($sitecode)
    {
        $nbsite = Sites::where('name__ie', $sitecode)->first();
        if(!isset($nbsite->id))
        {
            $this->addLog(0, "Unable to find NETBOX SITE.");
        }
        $this->addLog(1, "Successfully retreived NETBOX SITE.");
        $nbdevices = Devices::where('site_id', $nbsite->id)->where('name__empty', 'false')->where('exclude','config_context')->get();

        $mistsite = $nbsite->getMistSite();
        if(!isset($mistsite->id))
        {
            $this->addLog(0, "Unable to find MIST SITE.");
        }
        $this->addLog(1, "Successfully retreived MIST SITE.");
        $mistdevices = Device::where('vc', 'true')->get();
        $devices = [];
        foreach($nbdevices as $nbdevice)
        {
            $nbdevice->custom['mistdevice'] = null;
            if(!$nbdevice->serial)
            {
                continue;
            }
            foreach($mistdevices as $mistdevice)
            {
                if(strtolower($mistdevice->serial) == strtolower($nbdevice->serial))
                {
                    $nbdevice->custom['mistdevice'] = $mistdevice;
                    break;
                }
            }
            $devices[] = $nbdevice;
        }

        $return = [
            [
                'status'    =>  1,
                'data'  =>  [
                    'netboxbsite'    =>  $nbsite,
                    'mistsite'  =>  $mistsite,
                    'devices'   =>  $devices,
                ],
                'log'   =>   $this->logs,
            ]

        ];
        return $return;
    }
}
