<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Device\Device;
use App\Models\Mist\Site;
use App\Models\Mist\Device as MistDevice;
use App\Models\Device\Output;
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
        $allmistdevices = MistDevice::where('vc', 'true')->get();
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

    public function searchOutputs(Request $request)
    {
        $return = [];
        $submitted = $request->collect();
        if(!isset($submitted['search']))
        {
            return null;
        }
        $search = $submitted['search'];
        $deviceids = [];
        $array = [];
        $outputs = Output::where('data', 'like', '%' . $search . '%')->get();
        foreach($outputs as $output)
        {
            //$deviceids[] = $output->device_id;
            $array[$output->device_id]['outputs'][] = $output;
        }
        foreach($array as $id => $value)
        {
            unset($tmp);
            unset($ip);
            $device = Device::find($id);
            $nbdevice = $device->getNetboxDevice();
            if(!$nbdevice)
            {
                continue;
            }
            $tmp['id'] = $nbdevice->id;
            $tmp['name'] = $nbdevice->name;
            if(isset($nbdevice->device_type->model))
            {
                $tmp['model'] = $nbdevice->device_type->model;
            }
            $tmp['role'] = $nbdevice->role->name;
            if(isset($nbdevice->site->name))
            {
                $tmp['site'] = $nbdevice->site->name;
            }
            if(isset($nbdevice->location->name))
            {
                $tmp['location'] = $nbdevice->location->name;
            }
            $ip = $nbdevice->getIpAddress();
            if(isset($ip))
            {
                $tmp['ip'] = $ip;
            }
            foreach($value['outputs'] as $output)
            {
                $tmp['outputs'][] = ['type'=>$output->type, 'data'=>$output->data];
                //$tmp['outputs'][$output->type] = $output->data;
            }
            $return[] = $tmp;
        }
        usort($return, function($a, $b) {
            return $a['name'] <=> $b['name'];
        });
        return $return;
    }
}
