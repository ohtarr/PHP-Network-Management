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
use App\Jobs\SyncDeviceDnsJob;
use App\Jobs\SyncDeviceLibreNMSJob;
use Illuminate\Support\Facades\Log;
use App\Models\Log\Log as DbLog;

class ManagementController extends Controller
{
    public $logs = [];
    
    public function __construct()
    {
        $this->middleware('auth:api')->except(['syncNetboxDevice']);
    }

    public function addLog($status, $msg)
    {
        $username = auth()->user()?->userPrincipalName;
        $class = class_basename($this);
        $msg1 = $class . ": " . debug_backtrace()[1]['function'] . ": " . $msg;
        $this->logs[] = [
            'status' => $status,
            'msg'    => $msg,
        ];

        DbLog::log($msg1, $username, 'provisioning');
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
            $mistdevicesite = null;
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
                    'netboxsite'    =>  $nbsite,
                    'mistsite'  =>  $mistsite,
                    'devices'   =>  $devices,
                ],
                'log'   =>   $this->logs,
            ]

        ];
        return $return;
    }

    public function syncNetboxDevice(Request $request)
    {
        $model = $request->input('model');
        if ($model !== 'device') {
            return response()->json([
                'status'  => 0,
                'message' => "Webhook model '{$model}' is not 'device', ignoring.",
            ], 200);
        }

        $netboxDeviceId = $request->input('data.id');
        if (!$netboxDeviceId) {
            return response()->json([
                'status'  => 0,
                'message' => 'No device ID found in webhook payload (data.id).',
            ], 422);
        }

        $netboxDeviceId = (int) $netboxDeviceId;

        $event      = $request->input('event', 'updated');
        $deviceName = $request->input('data.name');

        Log::info('ManagementController@syncNetboxDevice: webhook received', [
            'event'            => $event,
            'netbox_device_id' => $netboxDeviceId,
            'name'             => $deviceName,
        ]);

        // For deleted events the device is already gone from Netbox — skip the
        // existence check and rely on the name from the webhook payload instead.
        if ($event !== 'deleted') {
            $device = Devices::find($netboxDeviceId);
            if (!isset($device->id)) {
                return response()->json([
                    'status'  => 0,
                    'message' => "Netbox device ID {$netboxDeviceId} not found.",
                ], 404);
            }
        }

        SyncDeviceDnsJob::dispatch($netboxDeviceId, $event, $deviceName);
        SyncDeviceLibreNMSJob::dispatch($netboxDeviceId, $event, $deviceName);

        return response()->json([
            'status'           => 1,
            'message'          => "SyncDeviceDnsJob and SyncDeviceLibreNMSJob dispatched for Netbox device ID {$netboxDeviceId} (event: {$event}).",
            'netbox_device_id' => $netboxDeviceId,
            'event'            => $event,
            'name'             => $deviceName ?? null,
        ]);
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
            unset($device);
            $device = Device::find($id);
            if(!$device)
            {
                continue;
            }
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
