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

    public function getNetboxSites()
    {
        return Sites::where('brief', 1)->get();
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
        $allmistdevices = Device::where('vc', 'true')->get();
        $devices = [];
        foreach($nbdevices as $nbdevice)
        {
            unset($vcmaster);
            unset($mistdevice);
            unset($devicecustom);
            unset($mistdevicesite);
            $nbdevice->custom['mistdevice'] = null;
            if(!$nbdevice->serial)
            {
                continue;
            }
            $mistdevice = $allmistdevices->where('serial', strtoupper($nbdevice->serial))->first();
            if(!isset($mistdevice->mac))
            {
                $mistdevice = $allmistdevices->where('serial', strtolower($nbdevice->serial))->first();
            }
            if(isset($mistdevice->mac))
            {
                //$devicecustom['site_id'] = null;

                //$mistdevice->custom = [
                //    'site_id'   =>  null,
                //];
                //$mistdevice->custom['site_id'] = null;
                
                
                
                

                //$nbdevice->custom['mistdevice']['custom'] = null;
                //$nbdevice->custom['mistdevice']['custom']['site_id'] = null;
                if(isset($mistdevice->site_id))
                {
                    //$devicecustom['site_id'] = $mistdevice->site_id;
                    $mistdevicesite = $mistdevice->site_id;
                    //$mistdevice->custom['site_id'] = $mistdevice->site_id;
                    //$nbdevice->custom['mistdevice']->custom['site_id'] = $mistdevice->site_id;
                } else {
                    if(isset($mistdevice->vc_mac))
                    {
                        $vcmaster = $allmistdevices->where('mac', $mistdevice->vc_mac)->first();
                        if(isset($vcmaster->site_id))
                        {
                            //$devicecustom['site_id'] = $vcmaster->site_id;
                            $mistdevicesite = $vcmaster->site_id;
                            //$mistdevice->custom['site_id'] = $vcmaster->site_id;
                            //$nbdevice->custom['mistdevice']['custom']['site_id'] = $mistdevice->site_id;
                        }
                    }
                }
                //$mistdevice->custom = $devicecustom;
                $nbdevice->custom['mistdevice'] = $mistdevice;
                $nbdevice->custom['mistdevicesite'] = $mistdevicesite;
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
